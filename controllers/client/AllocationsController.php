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
use App\Chat\Server;
use App\Chat\Allocation;
use App\SubuserPermissions;
use App\Helpers\ApiResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\Wings\Exceptions\WingsRequestException;
use App\Services\Wings\Exceptions\WingsConnectionException;
use App\Services\Wings\Exceptions\WingsAuthenticationException;

#[OA\Tag(name: 'Plugin - Pterodactyl API - Client Allocations', description: 'Client-facing allocation endpoints for the Pterodactyl compatibility API.')]
class AllocationsController extends ServersController
{
    #[OA\Get(
        path: '/api/client/servers/{identifier}/network/allocations',
        summary: 'List server allocations',
        description: 'Retrieves allocations assigned to the specified server.',
        tags: ['Plugin - Pterodactyl API - Client Allocations'],
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
            new OA\Response(response: 200, description: 'Allocation list.', content: new OA\JsonContent(type: 'object')),
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

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::ALLOCATION_READ);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $server = $context['server'];
        $allocations = Allocation::getByServerId((int) $server['id']);

        $data = array_map(
            fn (array $allocation): array => $this->formatAllocation($allocation, $server),
            $allocations
        );

        return ApiResponse::sendManualResponse([
            'object' => 'list',
            'data' => $data,
        ], 200);
    }

    #[OA\Post(
        path: '/api/client/servers/{identifier}/network/allocations',
        summary: 'Assign allocation',
        description: 'Automatically assigns a new allocation to the specified server.',
        tags: ['Plugin - Pterodactyl API - Client Allocations'],
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
            new OA\Response(response: 200, description: 'Allocation assigned.', content: new OA\JsonContent(type: 'object')),
            new OA\Response(response: 400, description: 'Allocation limit reached or no allocations available.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 500, description: 'Failed to assign allocation.'),
            new OA\Response(response: 502, description: 'Failed to synchronise with Wings daemon.'),
        ]
    )]
    public function create(Request $request, string $identifier): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::ALLOCATION_CREATE);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $server = $context['server'];
        $serverId = (int) $server['id'];

        $currentAllocations = count(Allocation::getByServerId($serverId));
        $allocationLimit = (int) ($server['allocation_limit'] ?? 0);
        if ($allocationLimit > 0 && $currentAllocations >= $allocationLimit) {
            return $this->displayError('Allocation limit reached.', 400);
        }

        $availableAllocations = array_filter(
            Allocation::getAvailable(200, 0),
            static fn (array $allocation): bool => (int) ($allocation['node_id'] ?? 0) === (int) ($server['node_id'] ?? 0)
        );

        if (empty($availableAllocations)) {
            return $this->displayError('No allocations are currently available for this server.', 400);
        }

        $selectedAllocation = array_shift($availableAllocations);
        if (!Allocation::assignToServer((int) $selectedAllocation['id'], $serverId)) {
            return $this->daemonErrorResponse(500, 'Failed to assign allocation to the server.');
        }

        $allocated = Allocation::getById((int) $selectedAllocation['id']);
        if ($allocated === null) {
            return $this->daemonErrorResponse(500, 'Failed to load the assigned allocation.');
        }

        $syncError = $this->syncServer($server);
        if ($syncError instanceof Response) {
            return $syncError;
        }

        $this->logServerActivity(
            $request,
            $context,
            'server:allocation.create',
            [
                'allocation_id' => (int) $allocated['id'],
                'ip' => $allocated['ip'] ?? null,
                'port' => (int) ($allocated['port'] ?? 0),
            ]
        );

        return ApiResponse::sendManualResponse($this->formatAllocation($allocated, $server), 200);
    }

    #[OA\Post(
        path: '/api/client/servers/{identifier}/network/allocations/{allocation}',
        summary: 'Update allocation notes',
        description: 'Updates the note for a specific allocation.',
        tags: ['Plugin - Pterodactyl API - Client Allocations'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'allocation',
                in: 'path',
                required: true,
                description: 'Allocation ID.',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['notes'],
                properties: [
                    new OA\Property(property: 'notes', type: 'string', maxLength: 191, nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Allocation updated.', content: new OA\JsonContent(type: 'object')),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 404, description: 'Allocation not found.'),
            new OA\Response(response: 422, description: 'Validation error.'),
            new OA\Response(response: 502, description: 'Failed to synchronise with Wings daemon.'),
        ]
    )]
    public function updateNotes(Request $request, string $identifier, string $allocationId): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::ALLOCATION_UPDATE);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $allocation = $this->resolveAllocation($context['server'], $allocationId);
        if ($allocation instanceof Response) {
            return $allocation;
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload) || !isset($payload['notes']) || !is_string($payload['notes'])) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The notes field is required.',
                        'meta' => [
                            'source_field' => 'notes',
                            'rule' => 'required',
                        ],
                    ],
                ],
            ], 422);
        }

        $notes = trim($payload['notes']);
        if (mb_strlen($notes) > 191) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The notes field may not be greater than 191 characters.',
                        'meta' => [
                            'source_field' => 'notes',
                            'rule' => 'max:191',
                        ],
                    ],
                ],
            ], 422);
        }

        if (!Allocation::update((int) $allocation['id'], ['notes' => $notes === '' ? null : $notes])) {
            return $this->daemonErrorResponse(500, 'Failed to update the allocation notes.');
        }

        $updated = Allocation::getById((int) $allocation['id']);
        if ($updated === null) {
            return $this->daemonErrorResponse(500, 'Failed to load the updated allocation.');
        }

        $syncError = $this->syncServer($context['server']);
        if ($syncError instanceof Response) {
            return $syncError;
        }

        $this->logServerActivity(
            $request,
            $context,
            'server:allocation.update',
            [
                'allocation_id' => (int) $updated['id'],
                'notes' => $notes,
            ]
        );

        return ApiResponse::sendManualResponse($this->formatAllocation($updated, $context['server']), 200);
    }

    #[OA\Post(
        path: '/api/client/servers/{identifier}/network/allocations/{allocation}/primary',
        summary: 'Set primary allocation',
        description: 'Sets the specified allocation as the primary allocation for the server.',
        tags: ['Plugin - Pterodactyl API - Client Allocations'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'allocation',
                in: 'path',
                required: true,
                description: 'Allocation ID.',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Primary allocation updated.', content: new OA\JsonContent(type: 'object')),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 404, description: 'Allocation not found.'),
            new OA\Response(response: 502, description: 'Failed to synchronise with Wings daemon.'),
        ]
    )]
    public function setPrimary(Request $request, string $identifier, string $allocationId): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::ALLOCATION_UPDATE);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $allocation = $this->resolveAllocation($context['server'], $allocationId);
        if ($allocation instanceof Response) {
            return $allocation;
        }

        if (!Server::updateServerById((int) $context['server']['id'], ['allocation_id' => (int) $allocation['id']])) {
            return $this->daemonErrorResponse(500, 'Failed to set the primary allocation.');
        }

        $context['server'] = Server::getServerById((int) $context['server']['id']) ?? $context['server'];

        $syncError = $this->syncServer($context['server']);
        if ($syncError instanceof Response) {
            return $syncError;
        }

        $updated = Allocation::getById((int) $allocation['id']);
        if ($updated === null) {
            return $this->daemonErrorResponse(500, 'Failed to load the updated allocation.');
        }

        $this->logServerActivity(
            $request,
            $context,
            'server:allocation.set-primary',
            [
                'allocation_id' => (int) $updated['id'],
            ]
        );

        return ApiResponse::sendManualResponse($this->formatAllocation($updated, $context['server']), 200);
    }

    #[OA\Delete(
        path: '/api/client/servers/{identifier}/network/allocations/{allocation}',
        summary: 'Delete allocation',
        description: 'Removes a non-primary allocation from the specified server.',
        tags: ['Plugin - Pterodactyl API - Client Allocations'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'allocation',
                in: 'path',
                required: true,
                description: 'Allocation ID.',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Allocation removed.'),
            new OA\Response(response: 400, description: 'Cannot delete primary allocation.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 404, description: 'Allocation not found.'),
            new OA\Response(response: 500, description: 'Failed to remove allocation.'),
            new OA\Response(response: 502, description: 'Failed to synchronise with Wings daemon.'),
        ]
    )]
    public function destroy(Request $request, string $identifier, string $allocationId): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::ALLOCATION_DELETE);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $allocation = $this->resolveAllocation($context['server'], $allocationId);
        if ($allocation instanceof Response) {
            return $allocation;
        }

        if ((int) $allocation['id'] === (int) $context['server']['allocation_id']) {
            return $this->displayError('Cannot delete the primary allocation for a server.', 400);
        }

        if (!Allocation::unassignFromServer((int) $allocation['id'])) {
            return $this->daemonErrorResponse(500, 'Failed to delete the allocation.');
        }

        $syncError = $this->syncServer($context['server']);
        if ($syncError instanceof Response) {
            return $syncError;
        }

        $this->logServerActivity(
            $request,
            $context,
            'server:allocation.delete',
            [
                'allocation_id' => (int) $allocation['id'],
            ]
        );

        return new Response('', 204);
    }

    private function resolveAllocation(array $server, string $allocationId): array | Response
    {
        if (!ctype_digit($allocationId)) {
            return $this->notFoundError('Allocation');
        }

        $allocation = Allocation::getById((int) $allocationId);
        if ($allocation === null || (int) ($allocation['server_id'] ?? 0) !== (int) $server['id']) {
            return $this->notFoundError('Allocation');
        }

        return $allocation;
    }

    private function syncServer(array $server): ?Response
    {
        $wings = $this->createWingsClient($server);
        if ($wings instanceof Response) {
            return $wings;
        }

        try {
            $response = $wings->getServer()->syncServer((string) $server['uuid']);
            if (!$response->isSuccessful()) {
                return $this->daemonErrorResponse($response->getStatusCode(), $response->getError());
            }
        } catch (WingsConnectionException | WingsAuthenticationException | WingsRequestException $e) {
            App::getInstance(true)->getLogger()->error('Failed to sync server allocations: ' . $e->getMessage());

            return $this->daemonErrorResponse(502);
        } catch (\Throwable $e) {
            App::getInstance(true)->getLogger()->error('Unexpected error syncing allocations: ' . $e->getMessage());

            return $this->daemonErrorResponse(502);
        }

        return null;
    }

    private function formatAllocation(array $allocation, array $server): array
    {
        $attributes = [
            'id' => (int) ($allocation['id'] ?? 0),
            'ip' => (string) ($allocation['ip'] ?? ''),
            'ip_alias' => $allocation['ip_alias'] !== null ? (string) $allocation['ip_alias'] : null,
            'port' => (int) ($allocation['port'] ?? 0),
            'notes' => $allocation['notes'] !== null ? (string) $allocation['notes'] : null,
            'is_default' => (int) ($allocation['id'] ?? 0) === (int) ($server['allocation_id'] ?? 0),
        ];

        return [
            'object' => 'allocation',
            'attributes' => $attributes,
        ];
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
