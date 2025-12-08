<?php

/*
 * This file is part of FeatherPanel.
 *
 * MIT License
 *
 * Copyright (c) 2025 MythicalSystems
 * Copyright (c) 2025 Cassian Gherman (NaysKutzu)
 * Copyright (c) 2018 - 2021 Dane Everitt <dane@daneeveritt.com> and Contributors
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace App\Addons\pterodactylpanelapi\controllers\client;

use App\App;
use App\Chat\Node;
use App\Chat\User;
use App\Chat\Spell;
use App\Chat\Server;
use App\Permissions;
use App\Chat\Subuser;
use App\Chat\Allocation;
use App\SubuserPermissions;
use App\Chat\ServerActivity;
use App\Chat\ServerVariable;
use App\Helpers\ApiResponse;
use App\Services\Wings\Wings;
use OpenApi\Attributes as OA;
use App\Config\ConfigInterface;
use App\Helpers\PermissionHelper;
use App\CloudFlare\CloudFlareRealIP;
use App\Services\Wings\Services\JwtService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\Wings\Exceptions\WingsRequestException;
use App\Services\Wings\Exceptions\WingsConnectionException;
use App\Services\Wings\Exceptions\WingsAuthenticationException;
use App\Addons\pterodactylpanelapi\middleware\PterodactylKeyAuth;

#[OA\Tag(name: 'Plugin - Pterodactyl API - Client Servers', description: 'Client-facing server endpoints for the Pterodactyl compatibility API.')]
class ServersController
{
    #[OA\Get(
        path: '/api/client',
        summary: 'List client servers',
        description: 'Returns servers owned by or shared with the authenticated client user in Pterodactyl response format.',
        tags: ['Plugin - Pterodactyl API - Client Servers'],
        parameters: [
            new OA\Parameter(
                name: 'page',
                description: 'Page number for pagination.',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)
            ),
            new OA\Parameter(
                name: 'per_page',
                description: 'Number of servers per page (max 100).',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 50)
            ),
            new OA\Parameter(
                name: 'include',
                description: 'Comma separated relationships to include (allocations, variables, egg, subusers).',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'search',
                description: 'Optional search term for server name/description.',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Server list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'object', type: 'string', example: 'list'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'meta', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden.'),
        ]
    )]
    public function index(Request $request): Response
    {
        $userResult = $this->resolveClientUser($request);
        if ($userResult instanceof Response) {
            return $userResult;
        }

        /** @var array $user */
        $user = $userResult;

        $perPage = (int) $request->query->get('per_page', 50);
        if ($perPage < 1) {
            $perPage = 1;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        $page = (int) $request->query->get('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $search = '';
        $filter = $request->query->get('filter');
        if (is_array($filter) && isset($filter['name'])) {
            $search = (string) $filter['name'];
        } else {
            $search = (string) $request->query->get('search', '');
        }
        $search = trim($search);

        $includeParam = (string) $request->query->get('include', '');
        $includeSet = $this->parseIncludes($includeParam);

        $serverIndex = [];

        $ownedServers = Server::getServersByOwnerId((int) $user['id']);
        foreach ($ownedServers as $server) {
            if (!$this->matchesSearch($server, $search)) {
                continue;
            }
            $serverIndex[(int) $server['id']] = [
                'server' => $server,
                'server_owner' => true,
            ];
        }

        $subuserRows = Subuser::getSubusersByUserId((int) $user['id']);
        foreach ($subuserRows as $subuserRow) {
            $server = Server::getServerById((int) $subuserRow['server_id']);
            if ($server === null) {
                continue;
            }
            if (!$this->matchesSearch($server, $search)) {
                continue;
            }
            $serverId = (int) $server['id'];
            if (!isset($serverIndex[$serverId])) {
                $serverIndex[$serverId] = [
                    'server' => $server,
                    'server_owner' => false,
                ];
            }
        }

        $serverEntries = array_values($serverIndex);
        usort($serverEntries, static fn (array $a, array $b): int => ((int) $a['server']['id']) <=> ((int) $b['server']['id']));

        $total = count($serverEntries);
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $paginated = array_slice($serverEntries, $offset, $perPage);

        $nodeCache = [];
        $spellCache = [];
        $serverVariableCache = [];
        $allocationCache = [];
        $userCache = [];

        $data = [];
        foreach ($paginated as $entry) {
            $server = $entry['server'];
            $data[] = $this->formatServer(
                $server,
                $entry['server_owner'],
                $includeSet,
                $nodeCache,
                $spellCache,
                $serverVariableCache,
                $allocationCache,
                $userCache
            );
        }

        $count = count($data);

        $meta = [
            'pagination' => [
                'total' => $total,
                'count' => $count,
                'per_page' => $perPage,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'links' => [],
            ],
        ];

        if ($page < $totalPages) {
            $meta['pagination']['links']['next'] = $this->buildPaginationLink($request, $page + 1, $perPage);
        }

        return ApiResponse::sendManualResponse([
            'object' => 'list',
            'data' => $data,
            'meta' => $meta,
        ], 200);
    }

    #[OA\Get(
        path: '/api/client/servers/{identifier}',
        summary: 'Get server details',
        description: 'Retrieves a specific server visible to the authenticated client user.',
        tags: ['Plugin - Pterodactyl API - Client Servers'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                description: 'Server UUID or short identifier.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'include',
                description: 'Comma separated relationships to include (allocations, variables, egg, subusers).',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Server details.',
                content: new OA\JsonContent(type: 'object')
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden.'),
            new OA\Response(response: 404, description: 'Server not found.'),
        ]
    )]
    public function show(Request $request, string $identifier): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $includeParam = (string) $request->query->get('include', '');
        $includeSet = $this->parseIncludes($includeParam);

        $nodeCache = [];
        $spellCache = [];
        $serverVariableCache = [];
        $allocationCache = [];
        $userCache = [];

        $serverData = $this->formatServer(
            $context['server'],
            $context['is_owner'],
            $includeSet,
            $nodeCache,
            $spellCache,
            $serverVariableCache,
            $allocationCache,
            $userCache
        );

        return ApiResponse::sendManualResponse([
            'object' => 'server',
            'attributes' => $serverData['attributes'],
            'relationships' => $serverData['relationships'] ?? [],
            'meta' => [
                'is_server_owner' => $context['is_owner'],
                'user_permissions' => $context['permissions'],
            ],
        ], 200);
    }

    #[OA\Get(
        path: '/api/client/servers/{identifier}/websocket',
        summary: 'Generate websocket credentials',
        description: 'Generates a Wings websocket token and socket URL for the specified server.',
        tags: ['Plugin - Pterodactyl API - Client Servers'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                description: 'Server UUID or short identifier.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Websocket token and socket URL.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'token', type: 'string'),
                                new OA\Property(property: 'socket', type: 'string', format: 'uri'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 404, description: 'Server or node not found.'),
            new OA\Response(response: 500, description: 'Failed to generate websocket token.'),
        ]
    )]
    public function websocket(Request $request, string $identifier): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::WEBSOCKET_CONNECT);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $server = $context['server'];
        $node = Node::getNodeById((int) $server['node_id']);
        if (!$node) {
            return $this->notFoundError('Node');
        }

        try {
            $app = App::getInstance(true);
            $config = $app->getConfig();
            $panelUrl = $config->getSetting(ConfigInterface::APP_URL, 'https://featherpanel.mythical.systems');
            $scheme = $node['scheme'] ?? 'http';
            $host = $node['fqdn'] ?? '127.0.0.1';
            $port = (int) ($node['daemonListen'] ?? 8080);
            $token = (string) ($node['daemon_token'] ?? '');

            $jwtService = new JwtService(
                $token,
                $panelUrl,
                $scheme . '://' . $host . ':' . $port
            );

            $permissions = $context['permissions'];
            if (!in_array('*', $permissions, true) && !in_array(SubuserPermissions::WEBSOCKET_CONNECT, $permissions, true)) {
                $permissions[] = SubuserPermissions::WEBSOCKET_CONNECT;
            }

            $jwt = $jwtService->generateApiToken(
                $server['uuid'],
                $context['user']['uuid'],
                $permissions
            );

            $socketScheme = $scheme === 'https' ? 'wss' : 'ws';
            $socket = $socketScheme . '://' . $host . ':' . $port . '/api/servers/' . $server['uuid'] . '/ws';

            return ApiResponse::sendManualResponse([
                'data' => [
                    'token' => $jwt,
                    'socket' => $socket,
                ],
            ], 200);
        } catch (\Throwable $e) {
            App::getInstance(true)->getLogger()->error('Failed to generate websocket token: ' . $e->getMessage());

            return $this->daemonErrorResponse(500, 'Failed to generate websocket token.');
        }
    }

    #[OA\Get(
        path: '/api/client/servers/{identifier}/resources',
        summary: 'Get server resource usage',
        description: 'Retrieves live resource utilisation for the specified server.',
        tags: ['Plugin - Pterodactyl API - Client Servers'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                description: 'Server UUID or short identifier.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Resource usage.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'object', type: 'string', example: 'stats'),
                        new OA\Property(property: 'attributes', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 404, description: 'Server not found.'),
            new OA\Response(response: 502, description: 'Failed to contact Wings daemon.'),
        ]
    )]
    public function resources(Request $request, string $identifier): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::WEBSOCKET_CONNECT);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $server = $context['server'];
        $wings = $this->createWingsClient($server);
        if ($wings instanceof Response) {
            return $wings;
        }

        try {
            $data = $wings->getConnection()->get('/api/servers/' . $server['uuid'] . '/resources');

            $currentState = (string) ($data['current_state'] ?? $data['state'] ?? 'offline');
            $resources = $data['resources'] ?? [];

            return ApiResponse::sendManualResponse([
                'object' => 'stats',
                'attributes' => [
                    'current_state' => $currentState,
                    'is_suspended' => $this->boolValue($server['suspended'] ?? ($server['status'] ?? '') === 'suspended'),
                    'resources' => [
                        'memory_bytes' => (int) ($resources['memory_bytes'] ?? 0),
                        'cpu_absolute' => (float) ($resources['cpu_absolute'] ?? 0),
                        'disk_bytes' => (int) ($resources['disk_bytes'] ?? 0),
                        'network_rx_bytes' => (int) ($resources['network']['rx_bytes'] ?? $resources['network_rx_bytes'] ?? 0),
                        'network_tx_bytes' => (int) ($resources['network']['tx_bytes'] ?? $resources['network_tx_bytes'] ?? 0),
                    ],
                ],
            ], 200);
        } catch (WingsConnectionException | WingsAuthenticationException | WingsRequestException $e) {
            App::getInstance(true)->getLogger()->error('Failed to fetch resources from Wings: ' . $e->getMessage());

            return $this->daemonErrorResponse(502);
        } catch (\Throwable $e) {
            App::getInstance(true)->getLogger()->error('Unexpected error fetching resources: ' . $e->getMessage());

            return $this->daemonErrorResponse(502);
        }
    }

    #[OA\Post(
        path: '/api/client/servers/{identifier}/command',
        summary: 'Send console command',
        description: 'Sends a command to the specified server via the Wings daemon.',
        tags: ['Plugin - Pterodactyl API - Client Servers'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                description: 'Server UUID or short identifier.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['command'],
                properties: [
                    new OA\Property(property: 'command', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 204, description: 'Command accepted for processing.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 422, description: 'Validation failure (missing command).'),
            new OA\Response(response: 502, description: 'Failed to communicate with Wings daemon.'),
        ]
    )]
    public function command(Request $request, string $identifier): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::CONTROL_CONSOLE);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload) || !isset($payload['command']) || !is_string($payload['command']) || trim($payload['command']) === '') {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The command field is required.',
                        'meta' => [
                            'source_field' => 'command',
                            'rule' => 'required',
                        ],
                    ],
                ],
            ], 422);
        }

        $command = trim($payload['command']);
        $server = $context['server'];

        $wings = $this->createWingsClient($server);
        if ($wings instanceof Response) {
            return $wings;
        }

        try {
            $response = $wings->getServer()->sendCommands($server['uuid'], [$command]);
            if (!$response->isSuccessful()) {
                return $this->daemonErrorResponse($response->getStatusCode(), $response->getError());
            }

            $this->logServerActivity(
                $request,
                $context,
                'server:command.send',
                [
                    'command' => $command,
                ]
            );

            return new Response('', 204);
        } catch (WingsConnectionException | WingsAuthenticationException | WingsRequestException $e) {
            App::getInstance(true)->getLogger()->error('Failed to send command to Wings: ' . $e->getMessage());

            return $this->daemonErrorResponse(502);
        } catch (\Throwable $e) {
            App::getInstance(true)->getLogger()->error('Unexpected error sending command: ' . $e->getMessage());

            return $this->daemonErrorResponse(502);
        }
    }

    #[OA\Post(
        path: '/api/client/servers/{identifier}/power',
        summary: 'Send power action',
        description: 'Sends a power signal (start/stop/restart/kill) to the specified server.',
        tags: ['Plugin - Pterodactyl API - Client Servers'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                description: 'Server UUID or short identifier.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['signal'],
                properties: [
                    new OA\Property(
                        property: 'signal',
                        type: 'string',
                        enum: ['start', 'stop', 'restart', 'kill']
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 204, description: 'Power action accepted.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 422, description: 'Invalid signal provided.'),
            new OA\Response(response: 502, description: 'Failed to communicate with Wings daemon.'),
        ]
    )]
    public function power(Request $request, string $identifier): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload) || !isset($payload['signal']) || !is_string($payload['signal'])) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The signal field is required.',
                        'meta' => [
                            'source_field' => 'signal',
                            'rule' => 'required',
                        ],
                    ],
                ],
            ], 422);
        }

        $signal = strtolower(trim($payload['signal']));
        $allowedSignals = ['start', 'stop', 'restart', 'kill'];
        if (!in_array($signal, $allowedSignals, true)) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The selected signal is invalid.',
                        'meta' => [
                            'source_field' => 'signal',
                            'rule' => 'in:' . implode(',', $allowedSignals),
                        ],
                    ],
                ],
            ], 422);
        }

        $permission = match ($signal) {
            'start' => SubuserPermissions::CONTROL_START,
            'stop' => SubuserPermissions::CONTROL_STOP,
            'restart' => SubuserPermissions::CONTROL_RESTART,
            'kill' => SubuserPermissions::CONTROL_CONSOLE,
            default => null,
        };

        if ($permission !== null) {
            $permissionCheck = $this->ensurePermission($context, $permission);
            if ($permissionCheck instanceof Response) {
                return $permissionCheck;
            }
        }

        $server = $context['server'];
        $wings = $this->createWingsClient($server);
        if ($wings instanceof Response) {
            return $wings;
        }

        try {
            $wingsResponse = match ($signal) {
                'start' => $wings->getServer()->startServer($server['uuid']),
                'stop' => $wings->getServer()->stopServer($server['uuid']),
                'restart' => $wings->getServer()->restartServer($server['uuid']),
                'kill' => $wings->getServer()->killServer($server['uuid']),
                default => null,
            };

            if ($wingsResponse === null) {
                return $this->daemonErrorResponse(500, 'Unknown power signal.');
            }

            if (!$wingsResponse->isSuccessful()) {
                return $this->daemonErrorResponse($wingsResponse->getStatusCode(), $wingsResponse->getError());
            }

            $this->logServerActivity(
                $request,
                $context,
                'server:power.' . $signal,
                [
                    'signal' => $signal,
                ]
            );

            return new Response('', 204);
        } catch (WingsConnectionException | WingsAuthenticationException | WingsRequestException $e) {
            App::getInstance(true)->getLogger()->error('Failed to send power signal to Wings: ' . $e->getMessage());

            return $this->daemonErrorResponse(502);
        } catch (\Throwable $e) {
            App::getInstance(true)->getLogger()->error('Unexpected error sending power signal: ' . $e->getMessage());

            return $this->daemonErrorResponse(502);
        }
    }

    #[OA\Post(
        path: '/api/client/servers/{identifier}/settings/rename',
        summary: 'Rename server',
        description: 'Renames the specified server.',
        tags: ['Plugin - Pterodactyl API - Client Servers'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                description: 'Server UUID or short identifier.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 191),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 204, description: 'Server renamed successfully.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 422, description: 'Validation error.'),
            new OA\Response(response: 500, description: 'Failed to update server name.'),
        ]
    )]
    public function rename(Request $request, string $identifier): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::SETTINGS_RENAME);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload) || !isset($payload['name']) || !is_string($payload['name'])) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The name field is required.',
                        'meta' => [
                            'source_field' => 'name',
                            'rule' => 'required',
                        ],
                    ],
                ],
            ], 422);
        }

        $name = trim($payload['name']);
        if ($name === '' || mb_strlen($name) > 191) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The name field may not be greater than 191 characters.',
                        'meta' => [
                            'source_field' => 'name',
                            'rule' => 'max:191',
                        ],
                    ],
                ],
            ], 422);
        }

        $server = $context['server'];
        $oldName = (string) ($server['name'] ?? '');
        $updated = Server::updateServerById((int) $server['id'], ['name' => $name]);
        if (!$updated) {
            return $this->daemonErrorResponse(500, 'Failed to update the server name.');
        }

        $this->logServerActivity(
            $request,
            $context,
            'server:settings.rename',
            [
                'old_name' => $oldName,
                'new_name' => $name,
            ]
        );

        return new Response('', 204);
    }

    #[OA\Post(
        path: '/api/client/servers/{identifier}/settings/reinstall',
        summary: 'Reinstall server',
        description: 'Triggers a reinstall of the specified server through the Wings daemon.',
        tags: ['Plugin - Pterodactyl API - Client Servers'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                description: 'Server UUID or short identifier.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Reinstall request accepted.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 502, description: 'Failed to communicate with Wings daemon.'),
        ]
    )]
    public function reinstall(Request $request, string $identifier): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::SETTINGS_REINSTALL);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $server = $context['server'];
        $wings = $this->createWingsClient($server);
        if ($wings instanceof Response) {
            return $wings;
        }

        try {
            $response = $wings->getServer()->reinstallServer($server['uuid']);
            if (!$response->isSuccessful()) {
                return $this->daemonErrorResponse($response->getStatusCode(), $response->getError());
            }

            $this->logServerActivity(
                $request,
                $context,
                'server:settings.reinstall',
                []
            );

            return new Response('', 204);
        } catch (WingsConnectionException | WingsAuthenticationException | WingsRequestException $e) {
            App::getInstance(true)->getLogger()->error('Failed to reinstall server via Wings: ' . $e->getMessage());

            return $this->daemonErrorResponse(502);
        } catch (\Throwable $e) {
            App::getInstance(true)->getLogger()->error('Unexpected error reinstalling server: ' . $e->getMessage());

            return $this->daemonErrorResponse(502);
        }
    }

    #[OA\Get(
        path: '/api/client/servers/{identifier}/startup',
        summary: 'List startup variables',
        description: 'Returns startup variables for the specified server along with rendered startup command.',
        tags: ['Plugin - Pterodactyl API - Client Servers'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                description: 'Server UUID or short identifier.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Startup variables.',
                content: new OA\JsonContent(type: 'object')
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 404, description: 'Server not found.'),
        ]
    )]
    public function startup(Request $request, string $identifier): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::STARTUP_READ);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $server = $context['server'];

        $serverVariableCache = [];
        $spellCache = [];

        $rawVariables = $this->getServerVariablesWithDetails((int) $server['id'], $serverVariableCache);

        $valueMap = [];
        $data = [];
        foreach ($rawVariables as $variable) {
            $env = (string) ($variable['env_variable'] ?? '');
            $resolvedValue = $this->resolveVariableServerValue($variable);
            if ($env !== '') {
                $valueMap[$env] = $resolvedValue;
            }

            if ($this->boolValue($variable['user_viewable'] ?? true)) {
                $data[] = $this->formatStartupVariable($variable, $resolvedValue);
            }
        }

        $spell = $this->getSpell((int) ($server['spell_id'] ?? 0), $spellCache);
        $rawStartup = (string) ($spell['startup'] ?? $server['startup'] ?? '');
        $startupCommand = $this->renderStartupCommand($server, $rawStartup, $valueMap);

        return ApiResponse::sendManualResponse([
            'object' => 'list',
            'data' => $data,
            'meta' => [
                'startup_command' => $startupCommand,
                'raw_startup_command' => $rawStartup,
            ],
        ], 200);
    }

    #[OA\Get(
        path: '/api/client/servers/{identifier}/activity',
        summary: 'List server activity',
        description: 'Returns paginated activity entries for the specified server.',
        tags: ['Plugin - Pterodactyl API - Client Servers'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                description: 'Server UUID or short identifier.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'page',
                description: 'Page number for pagination.',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)
            ),
            new OA\Parameter(
                name: 'per_page',
                description: 'Number of activity entries per page (max 100).',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 50)
            ),
            new OA\Parameter(
                name: 'filter[type]',
                description: 'Optional event filter.',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Activity list.',
                content: new OA\JsonContent(type: 'object')
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
        ]
    )]
    public function activity(Request $request, string $identifier): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::ACTIVITY_READ);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $perPage = (int) $request->query->get('per_page', 50);
        if ($perPage < 1) {
            $perPage = 1;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        $page = (int) $request->query->get('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $search = '';
        $filterParam = $request->query->all('filter');
        if (is_array($filterParam)) {
            if (isset($filterParam['type'])) {
                $search = (string) $filterParam['type'];
            } elseif (isset($filterParam['event'])) {
                $search = (string) $filterParam['event'];
            }
        }

        $activities = ServerActivity::getActivitiesWithPagination(
            $page,
            $perPage,
            $search,
            (int) $context['server']['id']
        );

        $activityRows = $activities['data'] ?? [];
        $data = array_map(
            fn (array $activity): array => $this->formatActivity($activity),
            is_array($activityRows) ? $activityRows : []
        );

        $pagination = [
            'total' => (int) ($activities['pagination']['total'] ?? count($activityRows)),
            'count' => count($data),
            'per_page' => $perPage,
            'current_page' => (int) ($activities['pagination']['current_page'] ?? $page),
            'total_pages' => (int) ($activities['pagination']['last_page'] ?? max(1, (int) ceil(($activities['pagination']['total'] ?? count($activityRows)) / $perPage))),
            'links' => [],
        ];

        if ($pagination['current_page'] < $pagination['total_pages']) {
            $pagination['links']['next'] = $this->buildPaginationLink($request, $pagination['current_page'] + 1, $perPage);
        }

        return ApiResponse::sendManualResponse([
            'object' => 'list',
            'data' => $data,
            'meta' => [
                'pagination' => $pagination,
            ],
        ], 200);
    }

    #[OA\Put(
        path: '/api/client/servers/{identifier}/startup/variable',
        summary: 'Update startup variable',
        description: 'Updates a single startup variable for the specified server.',
        tags: ['Plugin - Pterodactyl API - Client Servers'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                description: 'Server UUID or short identifier.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['key', 'value'],
                properties: [
                    new OA\Property(property: 'key', type: 'string'),
                    new OA\Property(property: 'value', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Updated startup variable.',
                content: new OA\JsonContent(type: 'object')
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 404, description: 'Variable not found.'),
            new OA\Response(response: 422, description: 'Validation failure.'),
            new OA\Response(response: 502, description: 'Failed to synchronise with Wings daemon.'),
        ]
    )]
    public function updateStartupVariable(Request $request, string $identifier): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::STARTUP_UPDATE);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request body must be valid JSON.',
                    ],
                ],
            ], 422);
        }

        if (!isset($payload['key']) || !is_string($payload['key']) || trim($payload['key']) === '') {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The key field is required.',
                        'meta' => [
                            'source_field' => 'key',
                            'rule' => 'required',
                        ],
                    ],
                ],
            ], 422);
        }

        if (!array_key_exists('value', $payload)) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The value field is required.',
                        'meta' => [
                            'source_field' => 'value',
                            'rule' => 'required',
                        ],
                    ],
                ],
            ], 422);
        }

        $key = trim((string) $payload['key']);
        $value = (string) $payload['value'];

        $server = $context['server'];
        $serverVariableCache = [];
        $variables = $this->getServerVariablesWithDetails((int) $server['id'], $serverVariableCache);

        $target = null;
        foreach ($variables as $variable) {
            if ((string) ($variable['env_variable'] ?? '') === $key) {
                $target = $variable;
                break;
            }
        }

        if ($target === null) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'NotFoundHttpException',
                        'status' => 404,
                        'detail' => 'The requested resource could not be found.',
                    ],
                ],
            ], 404);
        }

        if (!$this->boolValue($target['user_editable'] ?? false)) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ForbiddenException',
                        'status' => 403,
                        'detail' => 'This variable is not editable.',
                    ],
                ],
            ], 403);
        }

        $validationError = $this->validateVariableValue($value, (string) ($target['rules'] ?? ''), (string) ($target['field_type'] ?? ''));
        if ($validationError !== null) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => $validationError,
                        'meta' => [
                            'source_field' => 'value',
                        ],
                    ],
                ],
            ], 422);
        }

        $updateOk = ServerVariable::updateServerVariable((int) $target['id'], [
            'variable_value' => $value,
        ]);
        if (!$updateOk) {
            return $this->daemonErrorResponse(500, 'Failed to update the variable value.');
        }

        $wings = $this->createWingsClient($server);
        if ($wings instanceof Response) {
            return $wings;
        }

        try {
            $syncResponse = $wings->getServer()->syncServer($server['uuid']);
            if (!$syncResponse->isSuccessful()) {
                return $this->daemonErrorResponse($syncResponse->getStatusCode(), $syncResponse->getError());
            }
        } catch (WingsConnectionException | WingsAuthenticationException | WingsRequestException $e) {
            App::getInstance(true)->getLogger()->error('Failed to sync server after variable update: ' . $e->getMessage());

            return $this->daemonErrorResponse(502);
        } catch (\Throwable $e) {
            App::getInstance(true)->getLogger()->error('Unexpected error syncing server after variable update: ' . $e->getMessage());

            return $this->daemonErrorResponse(502);
        }

        $target['variable_value'] = $value;

        $this->logServerActivity(
            $request,
            $context,
            'server:startup.update',
            [
                'variable' => $key,
            ]
        );

        return ApiResponse::sendManualResponse($this->formatStartupVariable($target, $value), 200);
    }

    protected function resolveClientUser(Request $request): array | Response
    {
        $keyType = $request->attributes->get('api_key_type');
        if ($keyType === null) {
            $keyType = $request->attributes->get('pterodactyl_api_key_type');
        }

        if ($keyType !== 'client') {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    'code' => 'AuthorizationException',
                    'status' => 403,
                    'detail' => 'Forbidden.',
                ],
            ], 403);
        }

        $apiClient = PterodactylKeyAuth::getCurrentApiClient($request);
        if (!is_array($apiClient)) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    'code' => 'AuthenticationException',
                    'status' => 401,
                    'detail' => 'Unauthenticated.',
                ],
            ], 401);
        }

        $user = $request->attributes->get('user');
        if (!is_array($user) || !isset($user['id'])) {
            $ownerId = isset($apiClient['created_by']) ? (int) $apiClient['created_by'] : 0;
            if ($ownerId <= 0) {
                return ApiResponse::sendManualResponse([
                    'errors' => [
                        'code' => 'NotFoundHttpException',
                        'status' => 404,
                        'detail' => 'The requested resource could not be found.',
                    ],
                ], 404);
            }

            $user = User::getUserById($ownerId);
            if ($user === null) {
                return ApiResponse::sendManualResponse([
                    'errors' => [
                        'code' => 'NotFoundHttpException',
                        'status' => 404,
                        'detail' => 'The requested resource could not be found.',
                    ],
                ], 404);
            }

            $request->attributes->set('user', $user);
        }

        if (($user['deleted'] ?? 'false') === 'true') {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    'code' => 'AuthorizationException',
                    'status' => 403,
                    'detail' => 'Forbidden.',
                ],
            ], 403);
        }

        return $user;
    }

    protected function resolveServerContext(Request $request, string $identifier): array | Response
    {
        $userResult = $this->resolveClientUser($request);
        if ($userResult instanceof Response) {
            return $userResult;
        }

        /** @var array $user */
        $user = $userResult;

        $server = Server::getServerByUuidShort($identifier) ?? Server::getServerByUuid($identifier);
        if ($server === null) {
            return $this->notFoundError('Server');
        }

        $isOwner = (int) $server['owner_id'] === (int) $user['id'];
        $isAdmin = PermissionHelper::hasPermission($user['uuid'], Permissions::ADMIN_SERVERS_VIEW)
            || PermissionHelper::hasPermission($user['uuid'], Permissions::ADMIN_SERVERS_EDIT)
            || PermissionHelper::hasPermission($user['uuid'], Permissions::ADMIN_SERVERS_DELETE);

        $subuser = null;
        if (!$isOwner && !$isAdmin) {
            $subuser = Subuser::getSubuserByUserAndServer((int) $user['id'], (int) $server['id']);
            if ($subuser === null) {
                return ApiResponse::sendManualResponse([
                    'errors' => [
                        [
                            'code' => 'ForbiddenException',
                            'status' => 403,
                            'detail' => 'You do not have permission to access this server.',
                        ],
                    ],
                ], 403);
            }
        }

        $permissions = $this->buildUserPermissions($user, $server, $isOwner, $isAdmin, $subuser);

        return [
            'user' => $user,
            'server' => $server,
            'is_owner' => $isOwner,
            'is_admin' => $isAdmin,
            'subuser' => $subuser,
            'permissions' => $permissions,
        ];
    }

    protected function buildUserPermissions(array $user, array $server, bool $isOwner, bool $isAdmin, ?array $subuser): array
    {
        if ($isOwner || $isAdmin) {
            return ['*', 'admin.websocket.errors', 'admin.websocket.install'];
        }

        if ($subuser !== null) {
            $decoded = json_decode($subuser['permissions'] ?? '[]', true);

            return is_array($decoded) ? array_values(array_unique($decoded)) : [];
        }

        return [];
    }

    protected function parseIncludes(string $includeParam): array
    {
        $includes = array_filter(array_map('trim', explode(',', $includeParam)));
        $set = [
            'allocations' => true,
            'variables' => true,
            'egg' => false,
            'subusers' => false,
        ];

        foreach ($includes as $include) {
            if ($include === 'egg') {
                $set['egg'] = true;
            } elseif ($include === 'subusers') {
                $set['subusers'] = true;
            } elseif ($include === 'allocations') {
                $set['allocations'] = true;
            } elseif ($include === 'variables') {
                $set['variables'] = true;
            }
        }

        return $set;
    }

    protected function matchesSearch(array $server, string $search): bool
    {
        if ($search === '') {
            return true;
        }

        $name = (string) ($server['name'] ?? '');
        $description = (string) ($server['description'] ?? '');

        return str_contains(strtolower($name), strtolower($search))
            || str_contains(strtolower($description), strtolower($search));
    }

    protected function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));

            return in_array($value, ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    protected function decodeJsonField(mixed $value): ?array
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function getNode(int $nodeId, array &$cache): array
    {
        if ($nodeId <= 0) {
            return [];
        }

        if (!isset($cache[$nodeId])) {
            $cache[$nodeId] = Node::getNodeById($nodeId) ?? [];
        }

        return $cache[$nodeId];
    }

    protected function getSpell(int $spellId, array &$cache): array
    {
        if ($spellId <= 0) {
            return [];
        }

        if (!isset($cache[$spellId])) {
            $cache[$spellId] = Spell::getSpellById($spellId) ?? [];
        }

        return $cache[$spellId];
    }

    protected function getAllocation(int $allocationId, array &$cache): array
    {
        if ($allocationId <= 0) {
            return [];
        }

        if (!isset($cache[$allocationId])) {
            $cache[$allocationId] = Allocation::getAllocationById($allocationId) ?? [];
        }

        return $cache[$allocationId];
    }

    protected function getServerVariablesWithDetails(
        int $serverId,
        array &$serverVariableCache,
    ): array {
        if ($serverId <= 0) {
            return [];
        }

        if (!isset($serverVariableCache[$serverId])) {
            $serverVariableCache[$serverId] = ServerVariable::getServerVariablesWithDetails($serverId);
        }

        return $serverVariableCache[$serverId];
    }

    protected function getUser(int $userId, array &$cache): array
    {
        if ($userId <= 0) {
            return [];
        }

        if (!isset($cache[$userId])) {
            $cache[$userId] = User::getUserById($userId) ?? [];
        }

        return $cache[$userId];
    }

    protected function resolveSftpHost(array $node, array $allocation): string
    {
        if (!empty($node['fqdn'])) {
            return (string) $node['fqdn'];
        }

        if (!empty($node['public_ip_v4'])) {
            return (string) $node['public_ip_v4'];
        }

        if (!empty($node['public_ip_v6'])) {
            return (string) $node['public_ip_v6'];
        }

        if (!empty($allocation['ip_alias'])) {
            return (string) $allocation['ip_alias'];
        }

        return (string) ($allocation['ip'] ?? '');
    }

    protected function buildPaginationLink(Request $request, int $page, int $perPage): string
    {
        $query = $request->query->all();
        $query['page'] = $page;
        $query['per_page'] = $perPage;

        $qs = http_build_query($query);

        return rtrim($request->getSchemeAndHttpHost() . $request->getBaseUrl() . $request->getPathInfo(), '/') . ($qs !== '' ? '?' . $qs : '');
    }

    protected function gravatarUrl(string $email): string
    {
        $email = strtolower(trim($email));
        $hash = md5($email);

        return 'https://www.gravatar.com/avatar/' . $hash;
    }

    protected function resolveVariableServerValue(array $variable): string
    {
        if (array_key_exists('variable_value', $variable) && $variable['variable_value'] !== null && $variable['variable_value'] !== '') {
            return (string) $variable['variable_value'];
        }

        return (string) ($variable['default_value'] ?? '');
    }

    protected function formatStartupVariable(array $variable, string $serverValue): array
    {
        return [
            'object' => 'egg_variable',
            'attributes' => [
                'name' => (string) ($variable['name'] ?? ''),
                'description' => (string) ($variable['description'] ?? ''),
                'env_variable' => (string) ($variable['env_variable'] ?? ''),
                'default_value' => (string) ($variable['default_value'] ?? ''),
                'server_value' => $serverValue,
                'is_editable' => $this->boolValue($variable['user_editable'] ?? false),
                'rules' => (string) ($variable['rules'] ?? ''),
            ],
        ];
    }

    protected function renderStartupCommand(array $server, string $rawStartup, array $valueMap): string
    {
        if ($rawStartup === '') {
            return '';
        }

        $map = $valueMap;
        $map['SERVER_MEMORY'] = (string) ($server['memory'] ?? '');
        $map['SERVER_NAME'] = (string) ($server['name'] ?? '');

        return (string) preg_replace_callback(
            '/\{\{\s*([A-Z0-9_]+)\s*\}\}/',
            static function (array $matches) use ($map): string {
                $key = $matches[1];
                if (array_key_exists($key, $map)) {
                    return (string) $map[$key];
                }

                return $matches[0];
            },
            $rawStartup
        );
    }

    protected function validateVariableValue(string $value, string $rules, string $fieldType = ''): ?string
    {
        $rules = trim($rules);
        if ($rules === '') {
            return null;
        }

        $parts = explode('|', $rules);
        $required = in_array('required', $parts, true);
        $nullable = in_array('nullable', $parts, true);
        $isNumeric = in_array('numeric', $parts, true) || in_array('integer', $parts, true);

        if ($value === '') {
            if ($required) {
                return 'This field is required';
            }
            if ($nullable) {
                return null;
            }

            return null;
        }

        if ($isNumeric) {
            if (!preg_match('/^\d+$/', $value)) {
                return 'Must be numeric';
            }
        }

        foreach ($parts as $part) {
            if (preg_match('/^max:(\d+)$/', $part, $matchMax)) {
                $limit = (int) $matchMax[1];
                if ($isNumeric) {
                    if ((int) $value > $limit) {
                        return 'Must be less than or equal to ' . $limit;
                    }
                } else {
                    if (strlen($value) > $limit) {
                        return 'Must be at most ' . $limit . ' characters';
                    }
                }
                continue;
            }

            if (preg_match('/^min:(\d+)$/', $part, $matchMin)) {
                $limit = (int) $matchMin[1];
                if ($isNumeric) {
                    if ((int) $value < $limit) {
                        return 'Must be at least ' . $limit;
                    }
                } else {
                    if (strlen($value) < $limit) {
                        return 'Must be at least ' . $limit . ' characters';
                    }
                }
                continue;
            }

            if (str_starts_with($part, 'regex:')) {
                $pattern = substr($part, strlen('regex:'));
                if (@preg_match($pattern, '') === false) {
                    return 'Invalid regex rule';
                }
                if (preg_match($pattern, $value) !== 1) {
                    return 'Value does not match required format';
                }
            }
        }

        return null;
    }

    protected function logServerActivity(Request $request, array $context, string $event, array $metadata = []): void
    {
        $server = $context['server'] ?? null;
        if (!is_array($server)) {
            return;
        }

        $serverId = (int) ($server['id'] ?? 0);
        $nodeId = (int) ($server['node_id'] ?? 0);
        if ($serverId <= 0 || $nodeId <= 0) {
            return;
        }

        $user = $context['user'] ?? $request->attributes->get('user');
        $userId = is_array($user) && isset($user['id']) ? (int) $user['id'] : null;

        $payload = [
            'server_id' => $serverId,
            'node_id' => $nodeId,
            'event' => $event,
            'metadata' => array_merge(['source' => 'client_api'], $metadata),
        ];

        if ($userId !== null && $userId > 0) {
            $payload['user_id'] = $userId;
        }

        $ip = CloudFlareRealIP::getRealIP() ?? $request->getClientIp();
        if (is_string($ip) && $ip !== '') {
            $payload['ip'] = $ip;
        }

        try {
            ServerActivity::createActivity($payload);
        } catch (\Throwable $e) {
            App::getInstance(true)->getLogger()->error('Failed to log server activity: ' . $e->getMessage());
        }
    }

    protected function ensurePermission(array $context, string $permission): ?Response
    {
        if ($context['is_owner'] || in_array('*', $context['permissions'], true)) {
            return null;
        }

        if (in_array($permission, $context['permissions'], true)) {
            return null;
        }

        return ApiResponse::sendManualResponse([
            'errors' => [
                [
                    'code' => 'ForbiddenException',
                    'status' => 403,
                    'detail' => 'You do not have permission to perform this action.',
                ],
            ],
        ], 403);
    }

    protected function createWingsClient(array $server): Wings | Response
    {
        $nodeId = (int) ($server['node_id'] ?? 0);
        if ($nodeId <= 0) {
            return $this->notFoundError('Node');
        }

        $node = Node::getNodeById($nodeId);
        if (!$node) {
            return $this->notFoundError('Node');
        }

        $host = $node['fqdn'] ?? $node['public_ip_v4'] ?? $node['public_ip_v6'] ?? null;
        $port = (int) ($node['daemonListen'] ?? 8080);
        $scheme = $node['scheme'] ?? 'http';
        $token = (string) ($node['daemon_token'] ?? '');

        if ($host === null) {
            return $this->daemonErrorResponse(500, 'Node configuration is invalid.');
        }

        try {
            return new Wings($host, $port, $scheme, $token, 30);
        } catch (\Throwable $e) {
            App::getInstance(true)->getLogger()->error('Failed to initialize Wings client: ' . $e->getMessage());

            return $this->daemonErrorResponse(500, 'Failed to communicate with the Wings daemon.');
        }
    }

    protected function daemonErrorResponse(int $status, ?string $detail = null): Response
    {
        $detail = $detail ?: 'An error was encountered while processing this request.';
        $status = $status >= 400 ? $status : 502;

        $code = match ($status) {
            400 => 'BadRequestHttpException',
            401 => 'AuthenticationException',
            403 => 'ForbiddenException',
            404 => 'NotFoundHttpException',
            422 => 'ValidationException',
            default => 'HttpException',
        };

        return ApiResponse::sendManualResponse([
            'errors' => [
                [
                    'code' => $code,
                    'status' => (string) $status,
                    'detail' => $detail,
                ],
            ],
        ], $status);
    }

    protected function notFoundError(string $resource): Response
    {
        return ApiResponse::sendManualResponse([
            'errors' => [
                [
                    'code' => 'NotFoundHttpException',
                    'status' => 404,
                    'detail' => "The requested {$resource} could not be found.",
                ],
            ],
        ], 404);
    }

    protected function formatIso8601(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date(DATE_ATOM, $timestamp);
    }

    private function formatActivity(array $activity): array
    {
        $attributes = [
            'id' => $activity['uuid'] ?? $activity['id'] ?? null,
            'batch' => $activity['batch'] ?? null,
            'event' => (string) ($activity['event'] ?? ''),
            'is_api' => (bool) ($activity['is_api'] ?? false),
            'ip' => $activity['ip'] ?? null,
            'description' => $activity['description'] ?? null,
            'properties' => $this->normalizeActivityMetadata($activity['metadata'] ?? null),
            'has_additional_metadata' => $this->hasActivityMetadata($activity['metadata'] ?? null),
            'timestamp' => $this->formatIso8601($activity['timestamp'] ?? null),
        ];

        if (isset($activity['user']) && is_array($activity['user'])) {
            $attributes['user'] = [
                'username' => $activity['user']['username'] ?? null,
                'avatar' => $activity['user']['avatar'] ?? null,
                'role' => $activity['user']['role'] ?? null,
            ];
        }

        return [
            'object' => 'activity_log',
            'attributes' => $attributes,
        ];
    }

    private function normalizeActivityMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_string($metadata) && $metadata !== '') {
            $decoded = json_decode($metadata, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function hasActivityMetadata(mixed $metadata): bool
    {
        $normalized = $this->normalizeActivityMetadata($metadata);

        return !empty($normalized);
    }

    private function formatServer(
        array $server,
        bool $serverOwner,
        array $includeSet,
        array &$nodeCache,
        array &$spellCache,
        array &$serverVariableCache,
        array &$allocationCache,
        array &$userCache,
    ): array {
        $serverId = (int) $server['id'];
        $nodeId = (int) ($server['node_id'] ?? 0);
        $realmId = (int) ($server['realms_id'] ?? 0);
        $spellId = (int) ($server['spell_id'] ?? 0);
        $allocationId = (int) ($server['allocation_id'] ?? 0);

        $node = $this->getNode($nodeId, $nodeCache);
        $spell = $this->getSpell($spellId, $spellCache);
        $primaryAllocation = $this->getAllocation($allocationId, $allocationCache);

        $threads = $server['threads'] ?? null;
        if ($threads === '' || $threads === null) {
            $threadsValue = null;
        } elseif (is_numeric($threads)) {
            $threadsValue = (int) $threads;
        } else {
            $threadsValue = (string) $threads;
        }

        $eggFeatures = $this->decodeJsonField($spell['features'] ?? null);
        if ($eggFeatures === []) {
            $eggFeatures = null;
        }

        $attributes = [
            'server_owner' => $serverOwner,
            'identifier' => (string) ($server['uuidShort'] ?? $server['uuidshort'] ?? ''),
            'internal_id' => $serverId,
            'uuid' => (string) ($server['uuid'] ?? ''),
            'name' => (string) ($server['name'] ?? ''),
            'node' => $node['name'] ?? null,
            'is_node_under_maintenance' => $this->boolValue($node['maintenance_mode'] ?? false),
            'sftp_details' => [
                'ip' => $this->resolveSftpHost($node, $primaryAllocation),
                'port' => (int) ($node['daemonSFTP'] ?? 2022),
            ],
            'description' => $server['description'] ?? '',
            'limits' => [
                'memory' => (int) ($server['memory'] ?? 0),
                'swap' => (int) ($server['swap'] ?? 0),
                'disk' => (int) ($server['disk'] ?? 0),
                'io' => (int) ($server['io'] ?? 0),
                'cpu' => (int) ($server['cpu'] ?? 0),
                'threads' => $threadsValue,
                'oom_disabled' => $this->boolValue($server['oom_disabled'] ?? false),
            ],
            'invocation' => (string) ($server['startup'] ?? ''),
            'docker_image' => (string) ($server['image'] ?? ''),
            'egg_features' => $eggFeatures,
            'feature_limits' => [
                'databases' => (int) ($server['database_limit'] ?? 0),
                'allocations' => (int) ($server['allocation_limit'] ?? 0),
                'backups' => (int) ($server['backup_limit'] ?? 0),
            ],
            'status' => $server['status'] ?? null,
            'is_suspended' => $this->boolValue($server['suspended'] ?? ($server['status'] ?? '') === 'suspended'),
            'is_installing' => ($server['status'] ?? '') === 'installing',
            'is_transferring' => ($server['status'] ?? '') === 'transferring',
        ];

        $relationships = [];

        $allocations = Allocation::getByServerId($serverId);
        $relationships['allocations'] = [
            'object' => 'list',
            'data' => array_map(
                fn (array $allocation): array => [
                    'object' => 'allocation',
                    'attributes' => [
                        'id' => (int) $allocation['id'],
                        'ip' => $allocation['ip'] ?? '',
                        'ip_alias' => $allocation['ip_alias'] ?? null,
                        'port' => (int) $allocation['port'],
                        'notes' => $allocation['notes'] ?? null,
                        'is_default' => (int) $allocation['id'] === $allocationId,
                    ],
                ],
                $allocations
            ),
        ];

        $variables = $this->getServerVariablesWithDetails($serverId, $serverVariableCache);
        $relationships['variables'] = [
            'object' => 'list',
            'data' => array_map(
                fn (array $variable): array => [
                    'object' => 'egg_variable',
                    'attributes' => [
                        'name' => $variable['name'] ?? '',
                        'description' => $variable['description'] ?? '',
                        'env_variable' => $variable['env_variable'] ?? '',
                        'default_value' => $variable['default_value'] ?? '',
                        'server_value' => $variable['variable_value'] ?? '',
                        'is_editable' => $this->boolValue($variable['user_editable'] ?? false),
                        'rules' => $variable['rules'] ?? '',
                    ],
                ],
                $variables
            ),
        ];

        if ($includeSet['egg']) {
            $relationships['egg'] = [
                'object' => 'egg',
                'attributes' => [
                    'uuid' => $spell['uuid'] ?? null,
                    'name' => $spell['name'] ?? null,
                ],
            ];
        }

        if ($includeSet['subusers']) {
            $subusers = Subuser::getSubusersByServerId($serverId);
            $relationships['subusers'] = [
                'object' => 'list',
                'data' => array_map(
                    function (array $subuserRow) use (&$userCache): array {
                        $userId = (int) $subuserRow['user_id'];
                        $userData = $this->getUser($userId, $userCache);
                        $email = $userData['email'] ?? '';
                        $image = $userData['avatar'] ?? $this->gravatarUrl($email);

                        $permissions = json_decode($subuserRow['permissions'] ?? '[]', true);
                        if (!is_array($permissions)) {
                            $permissions = [];
                        }

                        return [
                            'object' => 'server_subuser',
                            'attributes' => [
                                'uuid' => $userData['uuid'] ?? null,
                                'username' => $userData['username'] ?? null,
                                'email' => $email,
                                'image' => $image,
                                '2fa_enabled' => $this->boolValue($userData['two_fa_enabled'] ?? false),
                                'created_at' => $subuserRow['created_at'] ?? null,
                                'permissions' => array_values($permissions),
                            ],
                        ];
                    },
                    $subusers
                ),
            ];
        }

        $result = [
            'object' => 'server',
            'attributes' => $attributes,
        ];

        if (!empty($relationships)) {
            $result['relationships'] = $relationships;
        }

        return $result;
    }
}
