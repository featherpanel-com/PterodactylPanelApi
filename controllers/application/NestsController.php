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

namespace App\Addons\pterodactylpanelapi\controllers\application;

use App\App;
use App\Chat\Node;
use App\Chat\Realm;
use App\Chat\Spell;
use App\Chat\Server;
use App\Chat\Location;
use App\Chat\SpellVariable;
use App\Helpers\ApiResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Addons\pterodactylpanelapi\utils\DateTimePtero;

#[OA\Tag(name: 'Plugin - Pterodactyl API - Nests', description: 'Nest management endpoints for Pterodactyl Panel API plugin')]
class NestsController
{
    #[OA\Get(
        path: '/api/application/nests',
        summary: 'List all nests',
        description: 'Retrieve a paginated list of all nests in the panel',
        tags: ['Plugin - Pterodactyl API - Nests'],
        parameters: [
            new OA\Parameter(
                name: 'page',
                description: 'Page number for pagination',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)
            ),
            new OA\Parameter(
                name: 'per_page',
                description: 'Number of items per page (max 100)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 50)
            ),
            new OA\Parameter(
                name: 'include',
                description: 'Comma-separated list of relationships to include (eggs, servers)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['eggs', 'servers', 'eggs,servers'])
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of nests retrieved successfully',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'object', type: 'string', example: 'list'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'object', type: 'string', example: 'nest'),
                                    new OA\Property(
                                        property: 'attributes',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer'),
                                            new OA\Property(property: 'uuid', type: 'string'),
                                            new OA\Property(property: 'author', type: 'string'),
                                            new OA\Property(property: 'name', type: 'string'),
                                            new OA\Property(property: 'description', type: 'string'),
                                            new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                            new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                                        ]
                                    ),
                                ]
                            )
                        ),
                        new OA\Property(
                            property: 'meta',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'pagination', type: 'object'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 500, description: 'Internal server error'),
        ]
    )]
    public function index(Request $request): Response
    {
        // Get pagination parameters
        $page = (int) $request->query->get('page', 1);
        $perPage = (int) $request->query->get('per_page', 50);

        // Validate pagination parameters
        if ($page < 1) {
            $page = 1;
        }
        if ($perPage < 1) {
            $perPage = 50;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        $offset = ($page - 1) * $perPage;

        // Get all nests
        $nests = Realm::getAll();
        $total = count($nests);

        // Apply pagination
        $paginatedNests = array_slice($nests, $offset, $perPage);

        // Check if relationships should be included
        $include = $request->query->get('include', '');
        $includeEggs = strpos($include, 'eggs') !== false;
        $includeServers = strpos($include, 'servers') !== false;

        // Build response data
        $data = [];
        foreach ($paginatedNests as $nest) {
            $nestData = [
                'object' => 'nest',
                'attributes' => [
                    'id' => (int) $nest['id'],
                    'uuid' => $nest['uuid'] ?? null,
                    'author' => 'help@mythical.systems',
                    'name' => $nest['name'],
                    'description' => $nest['description'],
                    'created_at' => DateTimePtero::format($nest['created_at']),
                    'updated_at' => DateTimePtero::format($nest['updated_at']),
                ],
            ];

            // Add relationships if requested
            $relationships = [];

            if ($includeEggs) {
                // Get eggs for this nest
                $eggs = Spell::getSpellsByRealmId($nest['id']);
                $eggData = [];
                foreach ($eggs as $egg) {
                    $eggData[] = [
                        'object' => 'egg',
                        'attributes' => [
                            'id' => (int) $egg['id'],
                            'uuid' => $egg['uuid'] ?? null,
                            'name' => $egg['name'],
                            'nest' => (int) $egg['realm_id'],
                            'author' => 'help@mythical.systems',
                            'description' => $egg['description'],
                            'docker_image' => $egg['docker_images'] ? json_decode($egg['docker_images'], true)[array_key_first(json_decode($egg['docker_images'], true))] ?? '' : '',
                            'docker_images' => $egg['docker_images'] ? json_decode($egg['docker_images'], true) : [],
                            'config' => [
                                'files' => $egg['config_files'] ? json_decode($egg['config_files'], true) : [],
                                'startup' => $egg['config_startup'] ? json_decode($egg['config_startup'], true) : [],
                                'stop' => $egg['config_stop'] ?? '',
                                'logs' => $egg['config_logs'] ? json_decode($egg['config_logs'], true) : [],
                                'file_denylist' => $egg['file_denylist'] ? json_decode($egg['file_denylist'], true) : [],
                                'extends' => $egg['config_from'] ? (int) $egg['config_from'] : null,
                            ],
                            'startup' => $egg['startup'] ?? '',
                            'script' => [
                                'privileged' => (bool) $egg['script_is_privileged'],
                                'install' => $egg['script_install'] ?? '',
                                'entry' => $egg['script_entry'] ?? 'ash',
                                'container' => $egg['script_container'] ?? 'alpine:3.4',
                                'extends' => $egg['copy_script_from'] ? (int) $egg['copy_script_from'] : null,
                            ],
                            'created_at' => DateTimePtero::format($egg['created_at']),
                            'updated_at' => DateTimePtero::format($egg['updated_at']),
                        ],
                    ];
                }
                $relationships['eggs'] = [
                    'object' => 'list',
                    'data' => $eggData,
                ];
            }

            if ($includeServers) {
                // Get servers for this nest with full data including environment variables
                $servers = $this->getServersWithEnvironmentVariables($nest['id']);
                $serverData = [];
                foreach ($servers as $serverId => $server) {
                    $serverData[] = [
                        'object' => 'server',
                        'attributes' => [
                            'id' => (int) $server['id'],
                            'external_id' => $server['external_id'],
                            'uuid' => $server['uuid'],
                            'identifier' => $server['uuidShort'] ?? substr($server['uuid'], 0, 8),
                            'name' => $server['name'],
                            'description' => $server['description'],
                            'status' => $server['status'],
                            'suspended' => (bool) $server['suspended'],
                            'limits' => [
                                'memory' => (int) $server['memory'],
                                'swap' => (int) $server['swap'],
                                'disk' => (int) $server['disk'],
                                'io' => (int) $server['io'],
                                'cpu' => (int) $server['cpu'],
                                'threads' => $server['threads'] ? (int) $server['threads'] : null,
                                'oom_disabled' => (bool) $server['oom_disabled'],
                            ],
                            'feature_limits' => [
                                'databases' => (int) $server['database_limit'],
                                'allocations' => (int) $server['allocation_limit'],
                                'backups' => (int) $server['backup_limit'],
                            ],
                            'user' => (int) $server['owner_id'],
                            'node' => (int) $server['node_id'],
                            'allocation' => (int) $server['allocation_id'],
                            'nest' => (int) $server['realms_id'],
                            'egg' => (int) $server['spell_id'],
                            'container' => [
                                'startup_command' => $server['startup'],
                                'image' => $server['image'],
                                'installed' => $server['installed_at'] ? 1 : 0,
                                'environment' => $this->buildEnvironmentVariables($server),
                            ],
                            'created_at' => DateTimePtero::format($server['created_at']),
                            'updated_at' => DateTimePtero::format($server['updated_at']),
                        ],
                    ];
                }
                $relationships['servers'] = [
                    'object' => 'list',
                    'data' => $serverData,
                ];
            }

            if (!empty($relationships)) {
                $nestData['attributes']['relationships'] = $relationships;
            }

            $data[] = $nestData;
        }

        // Calculate pagination
        $totalPages = ceil($total / $perPage);
        $links = [];

        if ($page > 1) {
            $queryParams = array_merge($request->query->all() ?? [], ['page' => $page - 1]);
            $links['previous'] = $request->getUriForPath($request->getPathInfo()) . '?' . http_build_query($queryParams);
        }

        if ($page < $totalPages) {
            $queryParams = array_merge($request->query->all() ?? [], ['page' => $page + 1]);
            $links['next'] = $request->getUriForPath($request->getPathInfo()) . '?' . http_build_query($queryParams);
        }

        $response = [
            'object' => 'list',
            'data' => $data,
            'meta' => [
                'pagination' => [
                    'total' => $total,
                    'count' => count($data),
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'links' => $links,
                ],
            ],
        ];

        return ApiResponse::sendManualResponse($response, 200);
    }

    /**
     * Get Nest Details - Retrieve detailed information about a specific nest.
     */
    #[OA\Get(
        path: '/api/application/nests/{nestId}',
        summary: 'Get nest details',
        description: 'Retrieve details for a specific nest',
        tags: ['Plugin - Pterodactyl API - Nests'],
        parameters: [
            new OA\Parameter(
                name: 'nestId',
                description: 'The ID of the nest',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'include',
                description: 'Comma-separated list of relationships to include (eggs, servers)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['eggs', 'servers', 'eggs,servers'])
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Nest details retrieved successfully',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'object', type: 'string', example: 'nest'),
                        new OA\Property(
                            property: 'attributes',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'uuid', type: 'string'),
                                new OA\Property(property: 'author', type: 'string'),
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'description', type: 'string'),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Nest not found'),
            new OA\Response(response: 500, description: 'Internal server error'),
        ]
    )]
    public function show(Request $request, $nestId): Response
    {
        // Get the nest
        $nest = Realm::getById($nestId);
        if (!$nest) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'NotFoundHttpException',
                        'status' => '404',
                        'detail' => 'The requested resource could not be found on the server.',
                    ],
                ],
            ], 404);
        }

        // Check if relationships should be included
        $include = $request->query->get('include', '');
        $includeEggs = strpos($include, 'eggs') !== false;
        $includeServers = strpos($include, 'servers') !== false;

        // Build response data
        $nestData = [
            'object' => 'nest',
            'attributes' => [
                'id' => (int) $nest['id'],
                'uuid' => $nest['uuid'] ?? null,
                'author' => 'help@mythical.systems',
                'name' => $nest['name'],
                'description' => $nest['description'],
                'created_at' => DateTimePtero::format($nest['created_at']),
                'updated_at' => DateTimePtero::format($nest['updated_at']),
            ],
        ];

        // Add relationships if requested
        $relationships = [];

        if ($includeEggs) {
            // Get eggs for this nest
            $eggs = Spell::getSpellsByRealmId($nest['id']);
            $eggData = [];
            foreach ($eggs as $egg) {
                $eggData[] = [
                    'object' => 'egg',
                    'attributes' => [
                        'id' => (int) $egg['id'],
                        'uuid' => $egg['uuid'] ?? null,
                        'name' => $egg['name'],
                        'nest' => (int) $egg['realm_id'],
                        'author' => 'help@mythical.systems',
                        'description' => $egg['description'],
                        'docker_image' => $egg['docker_images'] ? json_decode($egg['docker_images'], true)[array_key_first(json_decode($egg['docker_images'], true))] ?? '' : '',
                        'docker_images' => $egg['docker_images'] ? json_decode($egg['docker_images'], true) : [],
                        'config' => [
                            'files' => $egg['config_files'] ? json_decode($egg['config_files'], true) : [],
                            'startup' => $egg['config_startup'] ? json_decode($egg['config_startup'], true) : [],
                            'stop' => $egg['config_stop'] ?? '',
                            'logs' => $egg['config_logs'] ? json_decode($egg['config_logs'], true) : [],
                            'file_denylist' => $egg['file_denylist'] ? json_decode($egg['file_denylist'], true) : [],
                            'extends' => $egg['config_from'] ? (int) $egg['config_from'] : null,
                        ],
                        'startup' => $egg['startup'] ?? '',
                        'script' => [
                            'privileged' => (bool) $egg['script_is_privileged'],
                            'install' => $egg['script_install'] ?? '',
                            'entry' => $egg['script_entry'] ?? 'ash',
                            'container' => $egg['script_container'] ?? 'alpine:3.4',
                            'extends' => $egg['copy_script_from'] ? (int) $egg['copy_script_from'] : null,
                        ],
                        'created_at' => DateTimePtero::format($egg['created_at']),
                        'updated_at' => DateTimePtero::format($egg['updated_at']),
                    ],
                ];
            }
            $relationships['eggs'] = [
                'object' => 'list',
                'data' => $eggData,
            ];
        }

        if ($includeServers) {
            // Get servers for this nest with full data including environment variables
            $servers = $this->getServersWithEnvironmentVariables($nest['id']);
            $serverData = [];
            foreach ($servers as $serverId => $server) {
                $serverData[] = [
                    'object' => 'server',
                    'attributes' => [
                        'id' => (int) $server['id'],
                        'external_id' => $server['external_id'],
                        'uuid' => $server['uuid'],
                        'identifier' => $server['uuidShort'] ?? substr($server['uuid'], 0, 8),
                        'name' => $server['name'],
                        'description' => $server['description'],
                        'status' => $server['status'],
                        'suspended' => (bool) $server['suspended'],
                        'limits' => [
                            'memory' => (int) $server['memory'],
                            'swap' => (int) $server['swap'],
                            'disk' => (int) $server['disk'],
                            'io' => (int) $server['io'],
                            'cpu' => (int) $server['cpu'],
                            'threads' => $server['threads'] ? (int) $server['threads'] : null,
                            'oom_disabled' => (bool) $server['oom_disabled'],
                        ],
                        'feature_limits' => [
                            'databases' => (int) $server['database_limit'],
                            'allocations' => (int) $server['allocation_limit'],
                            'backups' => (int) $server['backup_limit'],
                        ],
                        'user' => (int) $server['owner_id'],
                        'node' => (int) $server['node_id'],
                        'allocation' => (int) $server['allocation_id'],
                        'nest' => (int) $server['realms_id'],
                        'egg' => (int) $server['spell_id'],
                        'container' => [
                            'startup_command' => $server['startup'],
                            'image' => $server['image'],
                            'installed' => $server['installed_at'] ? 1 : 0,
                            'environment' => $this->buildEnvironmentVariables($server),
                        ],
                        'created_at' => DateTimePtero::format($server['created_at']),
                        'updated_at' => DateTimePtero::format($server['updated_at']),
                    ],
                ];
            }
            $relationships['servers'] = [
                'object' => 'list',
                'data' => $serverData,
            ];
        }

        if (!empty($relationships)) {
            $nestData['attributes']['relationships'] = $relationships;
        }

        return ApiResponse::sendManualResponse($nestData, 200);
    }

    /**
     * List Nest Eggs - Retrieve all eggs within a specific nest.
     */
    #[OA\Get(
        path: '/api/application/nests/{nestId}/eggs',
        summary: 'List nest eggs',
        description: 'Retrieve a list of all eggs belonging to a specific nest',
        tags: ['Plugin - Pterodactyl API - Nests'],
        parameters: [
            new OA\Parameter(
                name: 'nestId',
                description: 'The ID of the nest',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'include',
                description: 'Comma-separated list of relationships to include (nest, servers, config, script, variables)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['nest', 'servers', 'config', 'script', 'variables'])
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of eggs retrieved successfully',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'object', type: 'string', example: 'list'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'object', type: 'string', example: 'egg'),
                                    new OA\Property(
                                        property: 'attributes',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer'),
                                            new OA\Property(property: 'uuid', type: 'string'),
                                            new OA\Property(property: 'name', type: 'string'),
                                            new OA\Property(property: 'author', type: 'string'),
                                            new OA\Property(property: 'description', type: 'string'),
                                            new OA\Property(property: 'docker_image', type: 'string'),
                                            new OA\Property(property: 'config', type: 'object'),
                                            new OA\Property(property: 'startup', type: 'string'),
                                            new OA\Property(property: 'script', type: 'object'),
                                            new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                            new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                                        ]
                                    ),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Nest not found'),
            new OA\Response(response: 500, description: 'Internal server error'),
        ]
    )]
    public function eggs(Request $request, $nestId): Response
    {
        // Get the nest
        $nest = Realm::getById($nestId);
        if (!$nest) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'NotFoundHttpException',
                        'status' => '404',
                        'detail' => 'The requested resource could not be found on the server.',
                    ],
                ],
            ], 404);
        }

        // Get pagination parameters
        $page = (int) $request->query->get('page', 1);
        $perPage = (int) $request->query->get('per_page', 50);

        // Validate pagination parameters
        if ($page < 1) {
            $page = 1;
        }
        if ($perPage < 1) {
            $perPage = 50;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        $offset = ($page - 1) * $perPage;

        // Get eggs for this nest
        $eggs = Spell::getSpellsByRealmId($nestId);
        $total = count($eggs);

        // Apply pagination
        $paginatedEggs = array_slice($eggs, $offset, $perPage);

        // Check if relationships should be included
        $include = $request->query->get('include', '');
        $includeVariables = strpos($include, 'variables') !== false;
        $includeNest = strpos($include, 'nest') !== false;

        // Build response data
        $data = [];
        foreach ($paginatedEggs as $egg) {
            $eggData = [
                'object' => 'egg',
                'attributes' => [
                    'id' => (int) $egg['id'],
                    'uuid' => $egg['uuid'] ?? null,
                    'name' => $egg['name'],
                    'nest' => (int) $egg['realm_id'],
                    'author' => 'help@mythical.systems',
                    'description' => $egg['description'],
                    'docker_image' => $egg['docker_images'] ? json_decode($egg['docker_images'], true)[array_key_first(json_decode($egg['docker_images'], true))] ?? '' : '',
                    'docker_images' => $egg['docker_images'] ? json_decode($egg['docker_images'], true) : [],
                    'config' => [
                        'files' => $egg['config_files'] ? json_decode($egg['config_files'], true) : [],
                        'startup' => $egg['config_startup'] ? json_decode($egg['config_startup'], true) : [],
                        'stop' => $egg['config_stop'] ?? '',
                        'logs' => $egg['config_logs'] ? json_decode($egg['config_logs'], true) : [],
                        'file_denylist' => $egg['file_denylist'] ? json_decode($egg['file_denylist'], true) : [],
                        'extends' => $egg['config_from'] ? (int) $egg['config_from'] : null,
                    ],
                    'startup' => $egg['startup'] ?? '',
                    'script' => [
                        'privileged' => (bool) $egg['script_is_privileged'],
                        'install' => $egg['script_install'] ?? '',
                        'entry' => $egg['script_entry'] ?? 'ash',
                        'container' => $egg['script_container'] ?? 'alpine:3.4',
                        'extends' => $egg['copy_script_from'] ? (int) $egg['copy_script_from'] : null,
                    ],
                    'created_at' => DateTimePtero::format($egg['created_at']),
                    'updated_at' => DateTimePtero::format($egg['updated_at']),
                ],
            ];

            // Add relationships if requested
            $relationships = [];

            if ($includeVariables) {
                // Get variables for this egg
                $variables = SpellVariable::getVariablesBySpellId($egg['id']);
                $variableData = [];
                foreach ($variables as $variable) {
                    $variableData[] = [
                        'object' => 'egg_variable',
                        'attributes' => [
                            'id' => (int) $variable['id'],
                            'egg_id' => (int) $variable['spell_id'],
                            'name' => $variable['name'],
                            'description' => $variable['description'],
                            'env_variable' => $variable['env_variable'],
                            'default_value' => $variable['default_value'],
                            'user_viewable' => (bool) $variable['user_viewable'],
                            'user_editable' => (bool) $variable['user_editable'],
                            'rules' => $variable['rules'],
                            'created_at' => DateTimePtero::format($variable['created_at']),
                            'updated_at' => DateTimePtero::format($variable['updated_at']),
                        ],
                    ];
                }
                $relationships['variables'] = [
                    'object' => 'list',
                    'data' => $variableData,
                ];
            }

            if ($includeNest) {
                $relationships['nest'] = [
                    'object' => 'nest',
                    'attributes' => [
                        'id' => (int) $nest['id'],
                        'uuid' => $nest['uuid'] ?? null,
                        'author' => 'help@mythical.systems',
                        'name' => $nest['name'],
                        'description' => $nest['description'],
                        'created_at' => DateTimePtero::format($nest['created_at']),
                        'updated_at' => DateTimePtero::format($nest['updated_at']),
                    ],
                ];
            }

            if (!empty($relationships)) {
                $eggData['attributes']['relationships'] = $relationships;
            }

            $data[] = $eggData;
        }

        // Calculate pagination
        $totalPages = ceil($total / $perPage);
        $links = [];

        if ($page > 1) {
            $queryParams = array_merge($request->query->all() ?? [], ['page' => $page - 1]);
            $links['previous'] = $request->getUriForPath($request->getPathInfo()) . '?' . http_build_query($queryParams);
        }

        if ($page < $totalPages) {
            $queryParams = array_merge($request->query->all() ?? [], ['page' => $page + 1]);
            $links['next'] = $request->getUriForPath($request->getPathInfo()) . '?' . http_build_query($queryParams);
        }

        $response = [
            'object' => 'list',
            'data' => $data,
            'meta' => [
                'pagination' => [
                    'total' => $total,
                    'count' => count($data),
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'links' => $links,
                ],
            ],
        ];

        return ApiResponse::sendManualResponse($response, 200);
    }

    /**
     * Get Egg Details - Retrieve detailed information about a specific egg within a nest.
     */
    #[OA\Get(
        path: '/api/application/nests/{nestId}/eggs/{eggId}',
        summary: 'Get egg details',
        description: 'Retrieve detailed information about a specific egg within a nest',
        tags: ['Plugin - Pterodactyl API - Nests'],
        parameters: [
            new OA\Parameter(
                name: 'nestId',
                description: 'The ID of the nest',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'eggId',
                description: 'The ID of the egg',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'include',
                description: 'Comma-separated list of relationships to include (nest, servers, config, script, variables)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['nest', 'servers', 'config', 'script', 'variables'])
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Egg details retrieved successfully',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'object', type: 'string', example: 'egg'),
                        new OA\Property(
                            property: 'attributes',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'uuid', type: 'string'),
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'author', type: 'string'),
                                new OA\Property(property: 'description', type: 'string'),
                                new OA\Property(property: 'docker_image', type: 'string'),
                                new OA\Property(property: 'config', type: 'object'),
                                new OA\Property(property: 'startup', type: 'string'),
                                new OA\Property(property: 'script', type: 'object'),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Nest or egg not found'),
            new OA\Response(response: 500, description: 'Internal server error'),
        ]
    )]
    public function eggDetails(Request $request, $nestId, $eggId): Response
    {
        // Get the nest
        $nest = Realm::getById($nestId);
        if (!$nest) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'NotFoundHttpException',
                        'status' => '404',
                        'detail' => 'The requested resource could not be found on the server.',
                    ],
                ],
            ], 404);
        }

        // Get the egg
        $egg = Spell::getSpellById($eggId);
        if (!$egg) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'NotFoundHttpException',
                        'status' => '404',
                        'detail' => 'The requested resource could not be found on the server.',
                    ],
                ],
            ], 404);
        }

        // Verify the egg belongs to the nest
        if ($egg['realm_id'] != $nestId) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'NotFoundHttpException',
                        'status' => '404',
                        'detail' => 'The requested resource could not be found on the server.',
                    ],
                ],
            ], 404);
        }

        // Check if relationships should be included
        $include = $request->query->get('include', '');
        $includeVariables = strpos($include, 'variables') !== false;
        $includeNest = strpos($include, 'nest') !== false;
        $includeServers = strpos($include, 'servers') !== false;
        $includeConfig = strpos($include, 'config') !== false;
        $includeScript = strpos($include, 'script') !== false;

        // Build response data
        $attributes = [
            'id' => (int) $egg['id'],
            'uuid' => $egg['uuid'] ?? null,
            'name' => $egg['name'],
            'nest' => (int) $egg['realm_id'],
            'author' => 'help@mythical.systems',
            'description' => $egg['description'],
            'docker_image' => $egg['docker_images'] ? json_decode($egg['docker_images'], true)[array_key_first(json_decode($egg['docker_images'], true))] ?? '' : '',
            'docker_images' => $egg['docker_images'] ? json_decode($egg['docker_images'], true) : [],
            'config' => [
                'files' => $egg['config_files'] ? json_decode($egg['config_files'], true) : [],
                'startup' => $egg['config_startup'] ? json_decode($egg['config_startup'], true) : [],
                'stop' => $egg['config_stop'] ?? '',
                'logs' => $egg['config_logs'] ? json_decode($egg['config_logs'], true) : [],
                'file_denylist' => $egg['file_denylist'] ? json_decode($egg['file_denylist'], true) : [],
                'extends' => $egg['config_from'] ? (int) $egg['config_from'] : null,
            ],
            'startup' => $egg['startup'] ?? '',
            'script' => [
                'privileged' => (bool) $egg['script_is_privileged'],
                'install' => $egg['script_install'] ?? '',
                'entry' => $egg['script_entry'] ?? 'ash',
                'container' => $egg['script_container'] ?? 'alpine:3.4',
                'extends' => $egg['copy_script_from'] ? (int) $egg['copy_script_from'] : null,
            ],
            'created_at' => DateTimePtero::format($egg['created_at']),
            'updated_at' => DateTimePtero::format($egg['updated_at']),
        ];

        // Add relationships if requested
        $relationships = [];

        if ($includeVariables) {
            // Get variables for this egg - get unique variables by env_variable name
            $variables = SpellVariable::getVariablesBySpellId($egg['id']);
            $uniqueVariables = [];
            $variableData = [];

            // Group by env_variable to get unique variables (like Pterodactyl does)
            foreach ($variables as $variable) {
                $envVar = $variable['env_variable'];
                if (!isset($uniqueVariables[$envVar])) {
                    $uniqueVariables[$envVar] = $variable;
                }
            }

            foreach ($uniqueVariables as $variable) {
                $variableData[] = [
                    'object' => 'egg_variable',
                    'attributes' => [
                        'id' => (int) $variable['id'],
                        'egg_id' => (int) $variable['spell_id'],
                        'name' => $variable['name'],
                        'description' => $variable['description'],
                        'env_variable' => $variable['env_variable'],
                        'default_value' => $variable['default_value'],
                        'user_viewable' => (bool) $variable['user_viewable'],
                        'user_editable' => (bool) $variable['user_editable'],
                        'rules' => $variable['rules'],
                        'created_at' => DateTimePtero::format($variable['created_at']),
                        'updated_at' => DateTimePtero::format($variable['updated_at']),
                    ],
                ];
            }
            $relationships['variables'] = [
                'object' => 'list',
                'data' => $variableData,
            ];
        }

        if ($includeNest) {
            $relationships['nest'] = [
                'object' => 'nest',
                'attributes' => [
                    'id' => (int) $nest['id'],
                    'uuid' => $nest['uuid'] ?? null,
                    'author' => 'help@mythical.systems',
                    'name' => $nest['name'],
                    'description' => $nest['description'],
                    'created_at' => DateTimePtero::format($nest['created_at']),
                    'updated_at' => DateTimePtero::format($nest['updated_at']),
                ],
            ];
        }

        if ($includeServers) {
            // Get servers for this egg with full data including environment variables
            $servers = $this->getServersWithEnvironmentVariables($nest['id']);
            $serverData = [];
            foreach ($servers as $serverId => $server) {
                // Only include servers that use this specific egg
                if ($server['spell_id'] == $egg['id']) {
                    $serverData[] = [
                        'object' => 'server',
                        'attributes' => [
                            'id' => (int) $server['id'],
                            'external_id' => $server['external_id'],
                            'uuid' => $server['uuid'],
                            'identifier' => $server['uuidShort'] ?? substr($server['uuid'], 0, 8),
                            'name' => $server['name'],
                            'description' => $server['description'],
                            'status' => $server['status'],
                            'suspended' => (bool) $server['suspended'],
                            'limits' => [
                                'memory' => (int) $server['memory'],
                                'swap' => (int) $server['swap'],
                                'disk' => (int) $server['disk'],
                                'io' => (int) $server['io'],
                                'cpu' => (int) $server['cpu'],
                                'threads' => $server['threads'] ? (int) $server['threads'] : null,
                                'oom_disabled' => (bool) $server['oom_disabled'],
                            ],
                            'feature_limits' => [
                                'databases' => (int) $server['database_limit'],
                                'allocations' => (int) $server['allocation_limit'],
                                'backups' => (int) $server['backup_limit'],
                            ],
                            'user' => (int) $server['owner_id'],
                            'node' => (int) $server['node_id'],
                            'allocation' => (int) $server['allocation_id'],
                            'nest' => (int) $server['realms_id'],
                            'egg' => (int) $server['spell_id'],
                            'container' => [
                                'startup_command' => $server['startup'],
                                'image' => $server['image'],
                                'installed' => $server['installed_at'] ? 1 : 0,
                                'environment' => $this->buildEnvironmentVariables($server),
                            ],
                            'created_at' => DateTimePtero::format($server['created_at']),
                            'updated_at' => DateTimePtero::format($server['updated_at']),
                        ],
                    ];
                }
            }
            $relationships['servers'] = [
                'object' => 'list',
                'data' => $serverData,
            ];
        }

        if ($includeConfig) {
            $relationships['config'] = [
                'object' => 'null_resource',
                'attributes' => null,
            ];
        }

        if ($includeScript) {
            $relationships['script'] = [
                'object' => 'null_resource',
                'attributes' => null,
            ];
        }

        if (!empty($relationships)) {
            $attributes['relationships'] = $relationships;
        }

        $eggData = [
            'object' => 'egg',
            'attributes' => $attributes,
        ];

        return ApiResponse::sendManualResponse($eggData, 200);
    }

    /**
     * Get servers with environment variables like ServersController does.
     */
    private function getServersWithEnvironmentVariables($realmId)
    {
        $pdo = App::getInstance(true)->getDatabase()->getPdo();

        // Build the main query with JOINs for environment variables (always included)
        $sql = 'SELECT 
			s.id,
			s.external_id,
			s.uuid,
			s.uuidShort,
			s.name,
			s.description,
			s.status,
			s.suspended,
			s.memory,
			s.swap,
			s.disk,
			s.io,
			s.cpu,
			s.threads,
			s.oom_disabled,
			s.allocation_limit,
			s.database_limit,
			s.backup_limit,
			s.owner_id,
			s.node_id,
			s.allocation_id,
			s.realms_id,
			s.spell_id,
			s.startup,
			s.image,
			s.installed_at,
			s.created_at,
			s.updated_at,
			sv.variable_value,
			spv.env_variable,
			spv.default_value
		FROM featherpanel_servers s
		LEFT JOIN featherpanel_server_variables sv ON s.id = sv.server_id
		LEFT JOIN featherpanel_spell_variables spv ON sv.variable_id = spv.id
		WHERE s.realms_id = :realm_id
		ORDER BY s.id ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':realm_id', $realmId, \PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Group results by server (always needed for variables)
        $servers = [];
        foreach ($results as $row) {
            $serverId = $row['id'];
            if (!isset($servers[$serverId])) {
                $servers[$serverId] = $row;
                $servers[$serverId]['variables'] = [];
                $servers[$serverId]['default_variables'] = [];
            }

            // Add server variable if it exists
            if ($row['env_variable'] && $row['variable_value'] !== null) {
                $servers[$serverId]['variables'][$row['env_variable']] = $row['variable_value'];
            }
        }

        // Get all default variables for each server's egg (to handle duplicates)
        foreach ($servers as $serverId => &$server) {
            if (isset($server['spell_id'])) {
                $defaultVarsSql = 'SELECT env_variable, default_value 
					FROM featherpanel_spell_variables 
					WHERE spell_id = :spell_id 
					AND id IN (
						SELECT MAX(id) 
						FROM featherpanel_spell_variables 
						WHERE spell_id = :spell_id 
						GROUP BY env_variable
					)';
                $defaultStmt = $pdo->prepare($defaultVarsSql);
                $defaultStmt->bindValue(':spell_id', $server['spell_id'], \PDO::PARAM_INT);
                $defaultStmt->execute();
                $defaultVars = $defaultStmt->fetchAll(\PDO::FETCH_ASSOC);

                foreach ($defaultVars as $var) {
                    if ($var['env_variable'] && $var['default_value'] !== null) {
                        $server['default_variables'][$var['env_variable']] = $var['default_value'];
                    }
                }
            }
        }

        return $servers;
    }

    /**
     * Build environment variables like Pterodactyl does.
     */
    private function buildEnvironmentVariables($server)
    {
        // Start with default variables from egg
        $environment = $server['default_variables'] ?? [];

        // Override with server-specific variables
        $environment = array_merge($environment, $server['variables'] ?? []);

        // Add Pterodactyl's automatic variables
        $environment['P_SERVER_UUID'] = $server['uuid'];
        $environment['P_SERVER_ALLOCATION_LIMIT'] = (string) ($server['allocation_limit'] ?? 0);

        // Add location info if available
        if (isset($server['node_id'])) {
            $node = Node::getNodeById($server['node_id']);
            if ($node && isset($node['location_id'])) {
                $location = Location::getById($node['location_id']);
                if ($location) {
                    $environment['P_SERVER_LOCATION'] = $location['name'];
                }
            }
        }

        return $environment;
    }
}
