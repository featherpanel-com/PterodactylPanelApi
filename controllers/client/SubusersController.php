<?php

/*
 * This file is part of FeatherPanel.
 *
 * Copyright (C) 2025 MythicalSystems Studios
 * Copyright (C) 2025 FeatherPanel Contributors
 * Copyright (C) 2025 Cassian Gherman (aka NaysKutzu)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See the LICENSE file or <https://www.gnu.org/licenses/>.
 */

namespace App\Addons\pterodactylpanelapi\controllers\client;

use App\App;
use App\Chat\User;
use App\Chat\Subuser;
use App\SubuserPermissions;
use App\Helpers\ApiResponse;
use OpenApi\Attributes as OA;
use App\Config\ConfigInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[OA\Tag(name: 'Plugin - Pterodactyl API - Client Subusers', description: 'Client-facing subuser endpoints for the Pterodactyl compatibility API.')]
class SubusersController extends ServersController
{
    #[OA\Get(
        path: '/api/client/servers/{identifier}/users',
        summary: 'List subusers',
        description: 'Retrieves subusers attached to the specified server.',
        tags: ['Plugin - Pterodactyl API - Client Subusers'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Subuser list.', content: new OA\JsonContent(type: 'object')),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
        ]
    )]
    public function list(Request $request, string $identifier): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::USER_READ);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $subusers = Subuser::getSubusersByServerId((int) $context['server']['id']);
        $data = [];
        foreach ($subusers as $subuser) {
            $data[] = $this->formatSubuser($subuser, $context['server']);
        }

        return ApiResponse::sendManualResponse([
            'object' => 'list',
            'data' => $data,
        ], 200);
    }

    #[OA\Post(
        path: '/api/client/servers/{identifier}/users',
        summary: 'Create subuser',
        description: 'Assigns a new subuser to the specified server.',
        tags: ['Plugin - Pterodactyl API - Client Subusers'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'permissions'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Subuser created.', content: new OA\JsonContent(type: 'object')),
            new OA\Response(response: 400, description: 'Validation error or cannot add user.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 404, description: 'User not found.'),
            new OA\Response(response: 500, description: 'Failed to create subuser.'),
        ]
    )]
    public function create(Request $request, string $identifier): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::USER_CREATE);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $config = App::getInstance(true)->getConfig();
        if ($config->getSetting(ConfigInterface::SERVER_ALLOW_SUBUSERS, 'true') === 'false') {
            return $this->displayError('Subusers are disabled on this host. Please contact your administrator to enable this feature.', 403);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->validationError('payload', 'The request body must be valid JSON.', 'json');
        }

        if (!isset($payload['email']) || !is_string($payload['email']) || trim($payload['email']) === '') {
            return $this->validationError('email', 'The email field is required.', 'required');
        }

        if (!filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->validationError('email', 'The email provided is invalid.', 'email');
        }

        if (!isset($payload['permissions']) || !is_array($payload['permissions'])) {
            return $this->validationError('permissions', 'The permissions field is required and must be an array.', 'array');
        }

        $user = User::getUserByEmail(trim($payload['email']));
        if ($user === null) {
            return $this->displayError('The requested resource could not be found.', 404);
        }

        if ((int) $user['id'] === (int) $context['server']['owner_id']) {
            return $this->displayError('You may not add the server owner as a subuser.', 400);
        }

        $currentUser = $request->get('user');
        if (is_array($currentUser) && (int) $currentUser['id'] === (int) $user['id']) {
            return $this->displayError('You may not add yourself as a subuser for this server.', 400);
        }

        $existing = Subuser::getSubuserByUserAndServer((int) $user['id'], (int) $context['server']['id']);
        if ($existing !== null) {
            return $this->displayError('That user is already assigned to this server.', 400);
        }

        $permissions = $this->normalizePermissions($payload['permissions']);
        if ($permissions instanceof Response) {
            return $permissions;
        }

        $createResult = Subuser::createSubuser([
            'user_id' => (int) $user['id'],
            'server_id' => (int) $context['server']['id'],
            'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($createResult === false) {
            return $this->daemonErrorResponse(500, 'Failed to create subuser.');
        }

        $subuser = Subuser::getSubuserByUserAndServer((int) $user['id'], (int) $context['server']['id']);
        if ($subuser === null) {
            return $this->daemonErrorResponse(500, 'Failed to load the created subuser.');
        }

        $this->logServerActivity(
            $request,
            $context,
            'server:subuser.create',
            [
                'user_uuid' => (string) ($user['uuid'] ?? ''),
                'email' => (string) ($user['email'] ?? ''),
            ]
        );

        return ApiResponse::sendManualResponse($this->formatSubuser($subuser, $context['server'], $user), 200);
    }

    #[OA\Get(
        path: '/api/client/servers/{identifier}/users/{subuser}',
        summary: 'View subuser',
        description: 'Retrieves details for a specific subuser.',
        tags: ['Plugin - Pterodactyl API - Client Subusers'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'subuser',
                in: 'path',
                required: true,
                description: 'Subuser UUID.',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Subuser details.', content: new OA\JsonContent(type: 'object')),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 404, description: 'Subuser not found.'),
        ]
    )]
    public function view(Request $request, string $identifier, string $subuserUuid): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::USER_READ);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $resolved = $this->resolveSubuser($context['server'], $subuserUuid);
        if ($resolved instanceof Response) {
            return $resolved;
        }

        return ApiResponse::sendManualResponse($this->formatSubuser($resolved['subuser'], $context['server'], $resolved['user']), 200);
    }

    #[OA\Post(
        path: '/api/client/servers/{identifier}/users/{subuser}',
        summary: 'Update subuser permissions',
        description: 'Updates the permissions for an existing subuser.',
        tags: ['Plugin - Pterodactyl API - Client Subusers'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'subuser',
                in: 'path',
                required: true,
                description: 'Subuser UUID.',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['permissions'],
                properties: [
                    new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Subuser updated.', content: new OA\JsonContent(type: 'object')),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 404, description: 'Subuser not found.'),
            new OA\Response(response: 422, description: 'Validation error.'),
            new OA\Response(response: 500, description: 'Failed to update subuser.'),
        ]
    )]
    public function update(Request $request, string $identifier, string $subuserUuid): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::USER_UPDATE);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $resolved = $this->resolveSubuser($context['server'], $subuserUuid);
        if ($resolved instanceof Response) {
            return $resolved;
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->validationError('payload', 'The request body must be valid JSON.', 'json');
        }

        if (!isset($payload['permissions']) || !is_array($payload['permissions'])) {
            return $this->validationError('permissions', 'The permissions field is required and must be an array.', 'array');
        }

        $permissions = $this->normalizePermissions($payload['permissions']);
        if ($permissions instanceof Response) {
            return $permissions;
        }

        if (!Subuser::updatePermissions((int) $resolved['subuser']['id'], $permissions)) {
            return $this->daemonErrorResponse(500, 'Failed to update subuser permissions.');
        }

        $updated = Subuser::getSubuserById((int) $resolved['subuser']['id']);
        if ($updated === null) {
            return $this->daemonErrorResponse(500, 'Failed to load the updated subuser.');
        }

        $this->logServerActivity(
            $request,
            $context,
            'server:subuser.update',
            [
                'user_uuid' => (string) ($resolved['user']['uuid'] ?? ''),
            ]
        );

        return ApiResponse::sendManualResponse($this->formatSubuser($updated, $context['server'], $resolved['user']), 200);
    }

    #[OA\Delete(
        path: '/api/client/servers/{identifier}/users/{subuser}',
        summary: 'Delete subuser',
        description: 'Removes a subuser from the specified server.',
        tags: ['Plugin - Pterodactyl API - Client Subusers'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'subuser',
                in: 'path',
                required: true,
                description: 'Subuser UUID.',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Subuser removed.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 404, description: 'Subuser not found.'),
            new OA\Response(response: 500, description: 'Failed to delete subuser.'),
        ]
    )]
    public function destroy(Request $request, string $identifier, string $subuserUuid): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::USER_DELETE);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $resolved = $this->resolveSubuser($context['server'], $subuserUuid);
        if ($resolved instanceof Response) {
            return $resolved;
        }

        if (!Subuser::deleteSubuser((int) $resolved['subuser']['id'])) {
            return $this->daemonErrorResponse(500, 'Failed to delete subuser.');
        }

        $this->logServerActivity(
            $request,
            $context,
            'server:subuser.delete',
            [
                'user_uuid' => (string) ($resolved['user']['uuid'] ?? ''),
            ]
        );

        return new Response('', 204);
    }

    /**
     * @return array{user: array, subuser: array}|Response
     */
    private function resolveSubuser(array $server, string $userUuid)
    {
        $user = User::getUserByUuid($userUuid);
        if ($user === null) {
            return $this->notFoundError('Subuser');
        }

        $subuser = Subuser::getSubuserByUserAndServer((int) $user['id'], (int) $server['id']);
        if ($subuser === null) {
            return $this->notFoundError('Subuser');
        }

        return [
            'user' => $user,
            'subuser' => $subuser,
        ];
    }

    /**
     * @param array<int, string> $permissions
     */
    private function normalizePermissions(array $permissions): array | Response
    {
        if (empty($permissions)) {
            return $this->validationError('permissions', 'At least one permission must be provided.', 'min:1');
        }

        $normalized = [];
        $validPermissions = SubuserPermissions::PERMISSIONS;

        foreach ($permissions as $rawPermission) {
            if (!is_string($rawPermission)) {
                return $this->validationError('permissions', 'Permissions must be an array of strings.', 'array');
            }

            $permission = trim($rawPermission);
            if ($permission === '') {
                return $this->validationError('permissions', 'Permissions must be non-empty strings.', 'string');
            }

            if ($permission === '*') {
                $normalized = $validPermissions;
                break;
            }

            if (str_ends_with($permission, '.*')) {
                $prefix = substr($permission, 0, -2);
                $matches = array_filter(
                    $validPermissions,
                    static fn (string $candidate): bool => str_starts_with($candidate, $prefix . '.')
                );

                if (empty($matches)) {
                    return $this->validationError('permissions', 'The permission ' . $permission . ' is invalid.', 'in');
                }

                foreach ($matches as $match) {
                    $normalized[] = $match;
                }

                continue;
            }

            if (!in_array($permission, $validPermissions, true)) {
                return $this->validationError('permissions', 'The permission ' . $permission . ' is invalid.', 'in');
            }

            $normalized[] = $permission;
        }

        $normalized[] = SubuserPermissions::WEBSOCKET_CONNECT;

        $normalized = array_values(array_unique($normalized));

        return $normalized;
    }

    private function formatSubuser(array $subuser, array $server, ?array $user = null): array
    {
        if ($user === null) {
            $user = User::getUserById((int) $subuser['user_id']) ?? [];
        }

        $permissions = $subuser['permissions'] ?? [];
        if (is_string($permissions)) {
            $decoded = json_decode($permissions, true);
            $permissions = is_array($decoded) ? $decoded : [];
        }

        $attributes = [
            'uuid' => (string) ($user['uuid'] ?? ''),
            'username' => (string) ($user['username'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'image' => $this->gravatarUrl((string) ($user['email'] ?? '')),
            '2fa_enabled' => $this->boolValue($user['two_fa_enabled'] ?? false),
            'created_at' => $this->formatIso8601($subuser['created_at'] ?? null),
            'permissions' => array_values(array_unique(array_map('strval', $permissions))),
        ];

        return [
            'object' => 'server_subuser',
            'attributes' => $attributes,
        ];
    }

    private function validationError(string $field, string $detail, string $rule): Response
    {
        return ApiResponse::sendManualResponse([
            'errors' => [
                [
                    'code' => 'ValidationException',
                    'status' => '422',
                    'detail' => $detail,
                    'meta' => [
                        'source_field' => $field,
                        'rule' => $rule,
                    ],
                ],
            ],
        ], 422);
    }

    private function displayError(string $detail, int $status): Response
    {
        return ApiResponse::sendManualResponse([
            'errors' => [
                [
                    'code' => 'DisplayException',
                    'status' => (string) $status,
                    'detail' => $detail,
                ],
            ],
        ], $status);
    }
}
