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
use App\Chat\Node;
use App\Chat\Backup;
use App\SubuserPermissions;
use App\Helpers\ApiResponse;
use OpenApi\Attributes as OA;
use App\Config\ConfigInterface;
use App\Services\Wings\Services\JwtService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\Wings\Exceptions\WingsRequestException;
use App\Services\Wings\Exceptions\WingsConnectionException;
use App\Services\Wings\Exceptions\WingsAuthenticationException;

#[OA\Tag(name: 'Plugin - Pterodactyl API - Client Backups', description: 'Client-facing backup endpoints for the Pterodactyl compatibility API.')]
class BackupsController extends ServersController
{
    #[OA\Get(
        path: '/api/client/servers/{identifier}/backups',
        summary: 'List server backups',
        description: 'Returns a paginated list of backups for the specified server.',
        tags: ['Plugin - Pterodactyl API - Client Backups'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'page',
                in: 'query',
                required: false,
                description: 'Page number for pagination.',
                schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                description: 'Number of backups per page (max 100).',
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Backup list.', content: new OA\JsonContent(type: 'object')),
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

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::BACKUP_READ);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $perPage = (int) $request->query->get('per_page', 20);
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

        $backups = Backup::getBackupsByServerId((int) $context['server']['id']);
        $total = count($backups);
        $totalPages = max(1, (int) ceil($total / max(1, $perPage)));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $perPage;
        $paginated = array_slice($backups, $offset, $perPage);

        $data = array_map(fn (array $backup): array => $this->formatBackup($backup), $paginated);

        $meta = [
            'pagination' => [
                'total' => $total,
                'count' => count($data),
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
        path: '/api/client/servers/{identifier}/backups/{backup}',
        summary: 'Get backup details',
        description: 'Retrieves details for a specific backup.',
        tags: ['Plugin - Pterodactyl API - Client Backups'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'backup',
                in: 'path',
                required: true,
                description: 'Backup UUID.',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Backup details.', content: new OA\JsonContent(type: 'object')),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 404, description: 'Backup not found.'),
        ]
    )]
    public function view(Request $request, string $identifier, string $backupUuid): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::BACKUP_READ);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $backup = $this->resolveServerBackup((int) $context['server']['id'], $backupUuid);
        if ($backup instanceof Response) {
            return $backup;
        }

        return ApiResponse::sendManualResponse($this->formatBackup($backup), 200);
    }

    #[OA\Post(
        path: '/api/client/servers/{identifier}/backups',
        summary: 'Create backup',
        description: 'Creates a new backup for the specified server.',
        tags: ['Plugin - Pterodactyl API - Client Backups'],
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
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'ignored', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'ignore', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'uuid', type: 'string', format: 'uuid'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 202, description: 'Backup created.', content: new OA\JsonContent(type: 'object')),
            new OA\Response(response: 400, description: 'Backup limit reached or invalid payload.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 409, description: 'Duplicate backup name.'),
            new OA\Response(response: 500, description: 'Failed to create backup.'),
            new OA\Response(response: 502, description: 'Failed to communicate with Wings daemon.'),
        ]
    )]
    public function create(Request $request, string $identifier): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::BACKUP_CREATE);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $server = $context['server'];
        $currentBackups = count(Backup::getBackupsByServerId((int) $server['id']));
        $backupLimit = (int) ($server['backup_limit'] ?? 1);

        if ($backupLimit >= 0 && $currentBackups >= $backupLimit) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'BadRequestHttpException',
                        'status' => 400,
                        'detail' => 'The server has reached its allocated backup limit.',
                    ],
                ],
            ], 400);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $name = isset($payload['name']) && is_string($payload['name']) && trim($payload['name']) !== ''
            ? trim($payload['name'])
            : 'Backup at ' . date('Y-m-d H:i:s');

        $ignored = [];
        if (isset($payload['ignored']) && is_array($payload['ignored'])) {
            $ignored = array_values(array_filter(array_map('strval', $payload['ignored']), static fn (string $value): bool => trim($value) !== ''));
        } elseif (isset($payload['ignore']) && is_array($payload['ignore'])) {
            $ignored = array_values(array_filter(array_map('strval', $payload['ignore']), static fn (string $value): bool => trim($value) !== ''));
        }

        $ignoredJson = json_encode($ignored, JSON_THROW_ON_ERROR);

        $backupUuid = $payload['uuid'] ?? $this->generateUuid();

        $backupData = [
            'server_id' => (int) $server['id'],
            'uuid' => $backupUuid,
            'name' => $name,
            'ignored_files' => $ignoredJson,
            'disk' => 'wings',
            'is_successful' => 0,
            'is_locked' => 1,
            'bytes' => 0,
            'sha256_hash' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $backupId = Backup::createBackup($backupData);
        if (!$backupId) {
            return $this->daemonErrorResponse(500, 'Failed to create backup record.');
        }

        $wings = $this->createWingsClient($server);
        if ($wings instanceof Response) {
            Backup::deleteBackup($backupId);

            return $wings;
        }

        try {
            $response = $wings->getServer()->createBackup($server['uuid'], 'wings', $backupUuid, $ignoredJson);
            if (!$response->isSuccessful()) {
                Backup::deleteBackup($backupId);

                return $this->daemonErrorResponse($response->getStatusCode(), $response->getError());
            }
        } catch (WingsConnectionException | WingsAuthenticationException | WingsRequestException $e) {
            Backup::deleteBackup($backupId);
            App::getInstance(true)->getLogger()->error('Failed to initiate backup on Wings: ' . $e->getMessage());

            return $this->daemonErrorResponse(502);
        } catch (\Throwable $e) {
            Backup::deleteBackup($backupId);
            App::getInstance(true)->getLogger()->error('Unexpected error initiating backup: ' . $e->getMessage());

            return $this->daemonErrorResponse(502);
        }

        $fresh = Backup::getBackupByUuid($backupUuid);
        if (!$fresh) {
            return $this->daemonErrorResponse(500, 'Failed to load created backup.');
        }

        $this->logServerActivity(
            $request,
            $context,
            'server:backup.start',
            [
                'backup_uuid' => $backupUuid,
                'name' => $name,
            ]
        );

        return ApiResponse::sendManualResponse($this->formatBackup($fresh), 202);
    }

    #[OA\Get(
        path: '/api/client/servers/{identifier}/backups/{backup}/download',
        summary: 'Generate backup download URL',
        description: 'Generates a signed download URL for the specified backup.',
        tags: ['Plugin - Pterodactyl API - Client Backups'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'backup',
                in: 'path',
                required: true,
                description: 'Backup UUID.',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Signed download URL.',
                content: new OA\JsonContent(type: 'object')
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 404, description: 'Backup not found.'),
            new OA\Response(response: 500, description: 'Failed to generate download URL.'),
        ]
    )]
    public function download(Request $request, string $identifier, string $backupUuid): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::BACKUP_DOWNLOAD);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $backup = $this->resolveServerBackup((int) $context['server']['id'], $backupUuid);
        if ($backup instanceof Response) {
            return $backup;
        }

        $server = $context['server'];
        $node = Node::getNodeById((int) $server['node_id']);
        if (!$node) {
            return $this->notFoundError('Node');
        }

        try {
            $jwtService = new JwtService(
                (string) ($node['daemon_token'] ?? ''),
                App::getInstance(true)->getConfig()->getSetting(ConfigInterface::APP_URL, 'https://panel.mythical.systems'),
                ($node['scheme'] ?? 'http') . '://' . ($node['fqdn'] ?? '127.0.0.1') . ':' . ($node['daemonListen'] ?? 8080)
            );

            $token = $jwtService->generateBackupToken(
                (string) $server['uuid'],
                (string) $context['user']['uuid'],
                [SubuserPermissions::BACKUP_DOWNLOAD],
                $backupUuid,
                'download'
            );

            $baseUrl = rtrim(($node['scheme'] ?? 'http') . '://' . ($node['fqdn'] ?? '127.0.0.1') . ':' . ($node['daemonListen'] ?? 8080), '/');
            $downloadUrl = $baseUrl . '/download/backup?token=' . $token . '&server=' . $server['uuid'] . '&backup=' . $backupUuid;

            $this->logServerActivity(
                $request,
                $context,
                'server:backup.download',
                [
                    'backup_uuid' => $backupUuid,
                ]
            );

            return ApiResponse::sendManualResponse([
                'object' => 'signed_url',
                'attributes' => [
                    'url' => $downloadUrl,
                ],
            ], 200);
        } catch (\Throwable $e) {
            App::getInstance(true)->getLogger()->error('Failed to generate backup download URL: ' . $e->getMessage());

            return $this->daemonErrorResponse(500, 'Failed to generate backup download URL.');
        }
    }

    #[OA\Delete(
        path: '/api/client/servers/{identifier}/backups/{backup}',
        summary: 'Delete backup',
        description: 'Deletes the specified backup from the panel and Wings.',
        tags: ['Plugin - Pterodactyl API - Client Backups'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'backup',
                in: 'path',
                required: true,
                description: 'Backup UUID.',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Backup deleted.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 404, description: 'Backup not found.'),
            new OA\Response(response: 423, description: 'Backup locked.'),
            new OA\Response(response: 500, description: 'Failed to delete backup.'),
            new OA\Response(response: 502, description: 'Failed to communicate with Wings daemon.'),
        ]
    )]
    public function destroy(Request $request, string $identifier, string $backupUuid): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::BACKUP_DELETE);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $backup = $this->resolveServerBackup((int) $context['server']['id'], $backupUuid);
        if ($backup instanceof Response) {
            return $backup;
        }

        if ($this->boolValue($backup['is_locked'] ?? false)) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'LockException',
                        'status' => 423,
                        'detail' => 'This backup is currently locked and cannot be deleted.',
                    ],
                ],
            ], 423);
        }

        $server = $context['server'];
        $wings = $this->createWingsClient($server);
        if ($wings instanceof Response) {
            return $wings;
        }

        try {
            $response = $wings->getServer()->deleteBackup((string) $server['uuid'], $backupUuid);
            if (!$response->isSuccessful()) {
                return $this->daemonErrorResponse($response->getStatusCode(), $response->getError());
            }
        } catch (WingsConnectionException | WingsAuthenticationException | WingsRequestException $e) {
            App::getInstance(true)->getLogger()->error('Failed to delete backup via Wings: ' . $e->getMessage());

            return $this->daemonErrorResponse(502);
        } catch (\Throwable $e) {
            App::getInstance(true)->getLogger()->error('Unexpected error deleting backup: ' . $e->getMessage());

            return $this->daemonErrorResponse(502);
        }

        if (!Backup::deleteBackup((int) $backup['id'])) {
            return $this->daemonErrorResponse(500, 'Failed to delete backup record.');
        }

        $this->logServerActivity(
            $request,
            $context,
            'server:backup.delete',
            [
                'backup_uuid' => $backupUuid,
            ]
        );

        return new Response('', 204);
    }

    private function formatBackup(array $backup): array
    {
        $ignored = $this->normalizeIgnoredFiles($backup['ignored_files'] ?? '[]');

        $attributes = [
            'uuid' => (string) ($backup['uuid'] ?? ''),
            'name' => (string) ($backup['name'] ?? ''),
            'ignored_files' => $ignored,
            'sha256_hash' => $backup['sha256_hash'] !== null ? (string) $backup['sha256_hash'] : null,
            'bytes' => (int) ($backup['bytes'] ?? 0),
            'created_at' => $this->formatIso8601($backup['created_at'] ?? null),
            'completed_at' => $this->formatIso8601($backup['completed_at'] ?? null),
        ];

        return [
            'object' => 'backup',
            'attributes' => $attributes,
        ];
    }

    private function normalizeIgnoredFiles(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value), static fn (string $item): bool => trim($item) !== ''));
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }

            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map('strval', $decoded), static fn (string $item): bool => trim($item) !== ''));
            }

            $parts = preg_split("/\r\n|\n|\r/", $trimmed) ?: [];

            return array_values(array_filter(array_map('trim', $parts), static fn (string $item): bool => $item !== ''));
        }

        return [];
    }

    private function resolveServerBackup(int $serverId, string $backupUuid): array | Response
    {
        $backup = Backup::getBackupByUuid($backupUuid);
        if ($backup === null || (int) $backup['server_id'] !== $serverId || ($backup['deleted_at'] ?? null) !== null) {
            return $this->notFoundError('Backup');
        }

        return $backup;
    }

    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0x0FFF) | 0x4000,
            mt_rand(0, 0x3FFF) | 0x8000,
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF)
        );
    }
}
