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
use App\SubuserPermissions;
use App\Chat\ServerDatabase;
use App\Helpers\ApiResponse;
use OpenApi\Attributes as OA;
use App\Chat\DatabaseInstance;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[OA\Tag(name: 'Plugin - Pterodactyl API - Client Databases', description: 'Client-facing database management endpoints for the Pterodactyl compatibility API.')]
class DatabasesController extends ServersController
{
    private const HASH_ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    #[OA\Get(
        path: '/api/client/servers/{identifier}/databases',
        summary: 'List server databases',
        description: 'Returns databases configured for the specified server.',
        tags: ['Plugin - Pterodactyl API - Client Databases'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'include',
                in: 'query',
                required: false,
                description: 'Comma separated includes (e.g. password).',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Database list.', content: new OA\JsonContent(type: 'object')),
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

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::DATABASE_READ);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $includeParam = (string) $request->query->get('include', '');
        $includePasswordRequested = str_contains($includeParam, 'password');

        $contextPermissions = $context['permissions'] ?? [];
        $hasPasswordPermission = in_array(SubuserPermissions::DATABASE_VIEW_PASSWORD, $contextPermissions, true)
            || in_array('*', $contextPermissions, true);

        $includePassword = $includePasswordRequested && $hasPasswordPermission;

        $databases = ServerDatabase::getServerDatabasesWithDetailsByServerId((int) $context['server']['id']);

        $data = array_map(
            function (array $database) use ($includePassword): array {
                return $this->formatDatabase($database, $includePassword, false);
            },
            $databases
        );

        return ApiResponse::sendManualResponse([
            'object' => 'list',
            'data' => $data,
        ], 200);
    }

    #[OA\Post(
        path: '/api/client/servers/{identifier}/databases',
        summary: 'Create database',
        description: 'Creates a new database for the specified server.',
        tags: ['Plugin - Pterodactyl API - Client Databases'],
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
                required: ['database', 'remote'],
                properties: [
                    new OA\Property(property: 'database', type: 'string'),
                    new OA\Property(property: 'remote', type: 'string', default: '%'),
                    new OA\Property(property: 'max_connections', type: 'integer', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Database created.', content: new OA\JsonContent(type: 'object')),
            new OA\Response(response: 400, description: 'Validation error or limit reached.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 409, description: 'Database name already exists.'),
            new OA\Response(response: 500, description: 'Failed to create database.'),
        ]
    )]
    public function store(Request $request, string $identifier): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::DATABASE_CREATE);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $limit = (int) ($context['server']['database_limit'] ?? 0);
        $currentCount = count(ServerDatabase::getServerDatabasesByServerId((int) $context['server']['id']));
        if ($limit > 0 && $currentCount >= $limit) {
            return $this->displayError('This server has reached its database limit.', 400);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->validationError('payload', 'The request body must be valid JSON.', 'json');
        }

        if (!isset($payload['database']) || !is_string($payload['database']) || trim($payload['database']) === '') {
            return $this->validationError('database', 'The database field is required.', 'required');
        }

        $databaseSlug = trim($payload['database']);
        if (!$this->isValidIdentifier($databaseSlug)) {
            return $this->validationError('database', 'The database field may only contain alphanumeric characters, dashes, and underscores.', 'alpha_dash');
        }

        $remote = isset($payload['remote']) && is_string($payload['remote']) ? trim($payload['remote']) : '%';
        if (!$this->isValidRemote($remote)) {
            return $this->validationError('remote', 'The remote field must be a valid hostname or IP address pattern.', 'regex');
        }

        $host = $this->resolveDatabaseHostForServer($context['server']);
        if ($host === null) {
            return $this->displayError('No database hosts are available for this server.', 400);
        }

        $databaseName = 's' . (int) $context['server']['id'] . '_' . $databaseSlug;
        if (ServerDatabase::getServerDatabaseByServerAndName((int) $context['server']['id'], $databaseName) !== null) {
            return $this->displayError('A database with that name already exists for this server.', 409);
        }

        $username = 'u' . (int) $context['server']['id'] . '_' . $this->generateRandomString(10);
        $password = $this->generateRandomString(24);

        try {
            $this->createDatabaseOnHost($host, $databaseName, $username, $password, $remote);
        } catch (\Throwable $e) {
            App::getInstance(true)->getLogger()->error('Failed to create database on host: ' . $e->getMessage());

            return $this->daemonErrorResponse(500, 'Unable to create database on the host server.');
        }

        $recordId = ServerDatabase::createServerDatabase([
            'server_id' => (int) $context['server']['id'],
            'database_host_id' => (int) $host['id'],
            'database' => $databaseName,
            'username' => $username,
            'password' => $password,
            'remote' => $remote,
            'max_connections' => (int) ($payload['max_connections'] ?? 0),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($recordId === false) {
            $this->deleteDatabaseFromHost($host, $databaseName, $username, $remote);

            return $this->daemonErrorResponse(500, 'Failed to persist the database record.');
        }

        $fresh = ServerDatabase::getServerDatabaseWithDetails((int) $recordId) ?? [
            'id' => $recordId,
            'server_id' => $context['server']['id'],
            'database_host_id' => $host['id'],
            'database' => $databaseName,
            'username' => $username,
            'password' => $password,
            'remote' => $remote,
            'max_connections' => (int) ($payload['max_connections'] ?? 0),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->logServerActivity(
            $request,
            $context,
            'server:database.create',
            [
                'database' => $databaseName,
                'username' => $username,
            ]
        );

        return ApiResponse::sendManualResponse(
            $this->formatDatabase($fresh, true, true),
            200
        );
    }

    #[OA\Post(
        path: '/api/client/servers/{identifier}/databases/{database}/rotate-password',
        summary: 'Rotate database password',
        description: 'Generates a new password for the specified database user.',
        tags: ['Plugin - Pterodactyl API - Client Databases'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'database',
                in: 'path',
                required: true,
                description: 'Opaque database identifier.',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Database password rotated.', content: new OA\JsonContent(type: 'object')),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 404, description: 'Database not found.'),
            new OA\Response(response: 500, description: 'Failed to rotate database password.'),
        ]
    )]
    public function rotatePassword(Request $request, string $identifier, string $hashedDatabaseId): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::DATABASE_UPDATE);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $databaseId = $this->decodeDatabaseId($hashedDatabaseId);
        if ($databaseId === null) {
            return $this->notFoundError('Database');
        }

        $database = ServerDatabase::getServerDatabaseWithDetails($databaseId);
        if ($database === null || (int) $database['server_id'] !== (int) $context['server']['id']) {
            return $this->notFoundError('Database');
        }

        $host = DatabaseInstance::getDatabaseById((int) $database['database_host_id']);
        if ($host === null) {
            return $this->daemonErrorResponse(500, 'Database host configuration is missing.');
        }

        $newPassword = $this->generateRandomString(24);

        try {
            $this->updateDatabasePasswordOnHost($host, $database['database'], $database['username'], $newPassword, $database['remote'] ?? '%');
        } catch (\Throwable $e) {
            App::getInstance(true)->getLogger()->error('Failed to rotate database password: ' . $e->getMessage());

            return $this->daemonErrorResponse(500, 'Unable to rotate the database password.');
        }

        if (!ServerDatabase::updateServerDatabase((int) $databaseId, [
            'password' => $newPassword,
        ])) {
            return $this->daemonErrorResponse(500, 'Failed to persist the new database password.');
        }

        $updated = ServerDatabase::getServerDatabaseWithDetails($databaseId) ?? array_merge($database, [
            'password' => $newPassword,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->logServerActivity(
            $request,
            $context,
            'server:database.rotate-password',
            [
                'database' => (string) ($database['database'] ?? ''),
            ]
        );

        return ApiResponse::sendManualResponse(
            $this->formatDatabase($updated, true, true),
            200
        );
    }

    #[OA\Delete(
        path: '/api/client/servers/{identifier}/databases/{database}',
        summary: 'Delete database',
        description: 'Deletes the specified database and its user.',
        tags: ['Plugin - Pterodactyl API - Client Databases'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'database',
                in: 'path',
                required: true,
                description: 'Opaque database identifier.',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Database deleted.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 404, description: 'Database not found.'),
            new OA\Response(response: 500, description: 'Failed to delete database.'),
        ]
    )]
    public function destroy(Request $request, string $identifier, string $hashedDatabaseId): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::DATABASE_DELETE);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $databaseId = $this->decodeDatabaseId($hashedDatabaseId);
        if ($databaseId === null) {
            return $this->notFoundError('Database');
        }

        $database = ServerDatabase::getServerDatabaseWithDetails($databaseId);
        if ($database === null || (int) $database['server_id'] !== (int) $context['server']['id']) {
            return $this->notFoundError('Database');
        }

        $host = DatabaseInstance::getDatabaseById((int) $database['database_host_id']);
        if ($host === null) {
            return $this->daemonErrorResponse(500, 'Database host configuration is missing.');
        }

        try {
            $this->deleteDatabaseFromHost($host, $database['database'], $database['username'], $database['remote'] ?? '%');
        } catch (\Throwable $e) {
            App::getInstance(true)->getLogger()->error('Failed to delete database from host: ' . $e->getMessage());

            return $this->daemonErrorResponse(500, 'Unable to remove the database from the host.');
        }

        if (!ServerDatabase::deleteServerDatabase($databaseId)) {
            return $this->daemonErrorResponse(500, 'Failed to delete the database record.');
        }

        $this->logServerActivity(
            $request,
            $context,
            'server:database.delete',
            [
                'database' => (string) ($database['database'] ?? ''),
            ]
        );

        return new Response('', 204);
    }

    private function formatDatabase(array $database, bool $includePassword, bool $includeRelationshipPassword): array
    {
        $host = DatabaseInstance::getDatabaseById((int) ($database['database_host_id'] ?? 0));

        $attributes = [
            'id' => $this->encodeDatabaseId((int) ($database['id'] ?? 0)),
            'host' => [
                'address' => (string) ($host['database_host'] ?? '127.0.0.1'),
                'port' => (int) ($host['database_port'] ?? 3306),
            ],
            'name' => (string) ($database['database'] ?? ''),
            'username' => (string) ($database['username'] ?? ''),
            'connections_from' => (string) ($database['remote'] ?? '%'),
            'max_connections' => (int) ($database['max_connections'] ?? 0),
        ];

        if ($includePassword || $includeRelationshipPassword) {
            $attributes['relationships'] = [
                'password' => [
                    'object' => 'database_password',
                    'attributes' => [
                        'password' => (string) ($database['password'] ?? ''),
                    ],
                ],
            ];
        }

        return [
            'object' => 'server_database',
            'attributes' => $attributes,
        ];
    }

    private function resolveDatabaseHostForServer(array $server): ?array
    {
        $nodeId = (int) ($server['node_id'] ?? 0);
        if ($nodeId > 0) {
            $hosts = DatabaseInstance::getDatabasesByNodeId($nodeId);
            if (!empty($hosts)) {
                return $hosts[0];
            }
        }

        $allHosts = DatabaseInstance::getAllDatabases();

        return $allHosts[0] ?? null;
    }

    private function encodeDatabaseId(int $id): string
    {
        if ($id <= 0) {
            return '0';
        }

        $alphabet = self::HASH_ALPHABET;
        $base = strlen($alphabet);
        $encoded = '';

        while ($id > 0) {
            $encoded = $alphabet[$id % $base] . $encoded;
            $id = intdiv($id, $base);
        }

        return $encoded;
    }

    private function decodeDatabaseId(string $hash): ?int
    {
        if ($hash === '' || !is_string($hash)) {
            return null;
        }

        $alphabet = self::HASH_ALPHABET;
        $base = strlen($alphabet);
        $length = strlen($hash);
        $value = 0;

        for ($i = 0; $i < $length; ++$i) {
            $position = strpos($alphabet, $hash[$i]);
            if ($position === false) {
                return null;
            }

            $value = $value * $base + $position;
        }

        return $value > 0 ? $value : null;
    }

    private function generateRandomString(int $length): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $randomString = '';

        for ($i = 0; $i < $length; ++$i) {
            $randomString .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $randomString;
    }

    private function createDatabaseOnHost(array $databaseHost, string $databaseName, string $username, string $password, string $remote): void
    {
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_TIMEOUT => 10,
        ];

        switch ($databaseHost['database_type']) {
            case 'mysql':
            case 'mariadb':
                $safeDbName = $this->quoteIdentifierMySQL($databaseName);
                $safeUser = $this->quoteIdentifierMySQL($username);
                $remoteHost = $this->sanitizeRemoteForSql($remote);
                $dsn = "mysql:host={$databaseHost['database_host']};port={$databaseHost['database_port']}";
                $pdo = new \PDO($dsn, $databaseHost['database_username'], $databaseHost['database_password'], $options);

                $pdo->exec("CREATE DATABASE IF NOT EXISTS {$safeDbName} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("CREATE USER IF NOT EXISTS {$safeUser}@'{$remoteHost}' IDENTIFIED BY '{$password}'");
                $pdo->exec("GRANT ALL PRIVILEGES ON {$safeDbName}.* TO {$safeUser}@'{$remoteHost}'");
                $pdo->exec('FLUSH PRIVILEGES');
                break;

            case 'postgresql':
                $safeDbName = $this->quoteIdentifier($databaseName);
                $safeUser = $this->quoteIdentifier($username);
                $dsn = "pgsql:host={$databaseHost['database_host']};port={$databaseHost['database_port']}";
                $pdo = new \PDO($dsn, $databaseHost['database_username'], $databaseHost['database_password'], $options);

                $pdo->exec("CREATE DATABASE {$safeDbName} WITH ENCODING 'UTF8'");
                $pdo->exec("CREATE USER {$safeUser} WITH PASSWORD '{$password}'");
                $pdo->exec("GRANT ALL PRIVILEGES ON DATABASE {$safeDbName} TO {$safeUser}");
                break;

            default:
                throw new \RuntimeException('Unsupported database type: ' . $databaseHost['database_type']);
        }
    }

    private function deleteDatabaseFromHost(array $databaseHost, string $databaseName, string $username, string $remote): void
    {
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_TIMEOUT => 10,
        ];

        switch ($databaseHost['database_type']) {
            case 'mysql':
            case 'mariadb':
                $safeDbName = $this->quoteIdentifierMySQL($databaseName);
                $safeUser = $this->quoteIdentifierMySQL($username);
                $remoteHost = $this->sanitizeRemoteForSql($remote);
                $dsn = "mysql:host={$databaseHost['database_host']};port={$databaseHost['database_port']}";
                $pdo = new \PDO($dsn, $databaseHost['database_username'], $databaseHost['database_password'], $options);

                $pdo->exec("REVOKE ALL PRIVILEGES ON {$safeDbName}.* FROM {$safeUser}@'{$remoteHost}'");
                $pdo->exec("DROP USER IF EXISTS {$safeUser}@'{$remoteHost}'");
                $pdo->exec("DROP DATABASE IF EXISTS {$safeDbName}");
                $pdo->exec('FLUSH PRIVILEGES');
                break;

            case 'postgresql':
                $safeDbName = $this->quoteIdentifier($databaseName);
                $safeUser = $this->quoteIdentifier($username);
                $dsn = "pgsql:host={$databaseHost['database_host']};port={$databaseHost['database_port']}";
                $pdo = new \PDO($dsn, $databaseHost['database_username'], $databaseHost['database_password'], $options);

                $pdo->exec("REVOKE ALL PRIVILEGES ON DATABASE {$safeDbName} FROM {$safeUser}");
                $pdo->exec("DROP DATABASE IF EXISTS {$safeDbName}");
                $pdo->exec("DROP USER IF EXISTS {$safeUser}");
                break;

            default:
                throw new \RuntimeException('Unsupported database type: ' . $databaseHost['database_type']);
        }
    }

    private function updateDatabasePasswordOnHost(array $databaseHost, string $databaseName, string $username, string $password, string $remote): void
    {
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_TIMEOUT => 10,
        ];

        switch ($databaseHost['database_type']) {
            case 'mysql':
            case 'mariadb':
                $safeUser = $this->quoteIdentifierMySQL($username);
                $remoteHost = $this->sanitizeRemoteForSql($remote);
                $dsn = "mysql:host={$databaseHost['database_host']};port={$databaseHost['database_port']}";
                $pdo = new \PDO($dsn, $databaseHost['database_username'], $databaseHost['database_password'], $options);

                $pdo->exec("ALTER USER {$safeUser}@'{$remoteHost}' IDENTIFIED BY '{$password}'");
                $pdo->exec('FLUSH PRIVILEGES');
                break;

            case 'postgresql':
                $safeUser = $this->quoteIdentifier($username);
                $dsn = "pgsql:host={$databaseHost['database_host']};port={$databaseHost['database_port']}";
                $pdo = new \PDO($dsn, $databaseHost['database_username'], $databaseHost['database_password'], $options);

                $pdo->exec("ALTER USER {$safeUser} WITH PASSWORD '{$password}'");
                break;

            default:
                throw new \RuntimeException('Unsupported database type: ' . $databaseHost['database_type']);
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    private function quoteIdentifierMySQL(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function sanitizeRemoteForSql(string $remote): string
    {
        return str_replace("'", '', $remote === '' ? '%' : $remote);
    }

    private function isValidIdentifier(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_\-]+$/', $value);
    }

    private function isValidRemote(string $remote): bool
    {
        if ($remote === '%') {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z0-9\.\-%:_]+$/', $remote);
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
