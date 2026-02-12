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

namespace App\Addons\pterodactylpanelapi\controllers\application;

use App\App;
use App\Chat\Server;
use App\Chat\ServerDatabase;
use App\Helpers\ApiResponse;
use OpenApi\Attributes as OA;
use App\Chat\DatabaseInstance;
use App\Config\ConfigInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Addons\pterodactylpanelapi\utils\DateTimePtero;

#[OA\Tag(name: 'Plugin - Pterodactyl API - Databases', description: 'Database management endpoints for Pterodactyl Panel API plugin')]
class DatabasesController
{
    #[OA\Get(
        path: '/api/application/servers/{serverId}/databases',
        summary: 'List server databases',
        description: 'Retrieve a paginated list of all databases for a specific server',
        tags: ['Plugin - Pterodactyl API - Databases'],
        parameters: [
            new OA\Parameter(
                name: 'serverId',
                description: 'The UUID or ID of the server',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
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
                description: 'Comma-separated list of relationships to include (password, host)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['password', 'host', 'password,host'])
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of server databases retrieved successfully',
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
                                    new OA\Property(property: 'object', type: 'string', example: 'server_database'),
                                    new OA\Property(
                                        property: 'attributes',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer'),
                                            new OA\Property(property: 'server', type: 'integer'),
                                            new OA\Property(property: 'host', type: 'integer'),
                                            new OA\Property(property: 'database', type: 'string'),
                                            new OA\Property(property: 'username', type: 'string'),
                                            new OA\Property(property: 'remote', type: 'string'),
                                            new OA\Property(property: 'max_connections', type: 'integer'),
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
            new OA\Response(response: 404, description: 'Server not found'),
            new OA\Response(response: 500, description: 'Internal server error'),
        ]
    )]
    public function index(Request $request, $serverId): Response
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

        // Get the server
        $server = Server::getServerByUuid($serverId);
        if (!$server) {
            // Try by ID if UUID fails
            $server = Server::getServerById($serverId);
        }

        if (!$server) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'NotFoundHttpException',
                        'status' => '404',
                        'detail' => 'The requested resource does not exist on this server.',
                    ],
                ],
            ], 404);
        }

        // Get databases for this server with pagination
        $databases = ServerDatabase::getServerDatabasesWithDetailsByServerId($server['id']);

        // Get total count for pagination
        $total = count($databases);

        // Apply pagination
        $databases = array_slice($databases, $offset, $perPage);
        $totalPages = ceil($total / $perPage);

        // Check if relationships should be included
        // Get include parameter - handle both array and string formats
        $allParams = $request->query->all();
        $includeParam = $allParams['include'] ?? '';
        if (is_array($includeParam)) {
            $include = implode(',', $includeParam);
        } else {
            $include = $includeParam;
        }
        $includePassword = strpos($include, 'password') !== false;
        $includeHost = strpos($include, 'host') !== false;

        // Build response data
        $data = [];
        foreach ($databases as $database) {
            $databaseData = [
                'object' => 'server_database',
                'attributes' => [
                    'id' => (int) $database['id'],
                    'server' => (int) $database['server_id'],
                    'host' => (int) $database['database_host_id'],
                    'database' => $database['database'],
                    'username' => $database['username'],
                    'remote' => $database['remote'],
                    'max_connections' => (int) $database['max_connections'],
                    'created_at' => DateTimePtero::format($database['created_at']),
                    'updated_at' => DateTimePtero::format($database['updated_at']),
                ],
            ];

            // Add relationships if requested
            $relationships = [];

            if ($includePassword) {
                $relationships['password'] = [
                    'object' => 'database_password',
                    'attributes' => [
                        'password' => $database['password'],
                    ],
                ];
            }

            if ($includeHost) {
                // Get the full host details
                $host = DatabaseInstance::getDatabaseById($database['database_host_id']);
                if ($host) {
                    $relationships['host'] = [
                        'object' => 'database_host',
                        'attributes' => [
                            'id' => (int) $host['id'],
                            'name' => $host['name'],
                            'host' => $host['database_host'],
                            'port' => (int) $host['database_port'],
                            'username' => $host['database_username'],
                            'node' => (int) $host['node_id'],
                            'created_at' => DateTimePtero::format($host['created_at'] ?? '2024-01-01 00:00:00'),
                            'updated_at' => DateTimePtero::format($host['updated_at'] ?? '2024-01-01 00:00:00'),
                        ],
                    ];
                }
            }

            if (!empty($relationships)) {
                $databaseData['attributes']['relationships'] = $relationships;
            }

            $data[] = $databaseData;
        }

        // Build pagination links
        $links = [];
        $baseUrl = App::getInstance(true)->getConfig()->getSetting(ConfigInterface::APP_URL, 'http://localhost');
        $currentParams = $request->query->all() ?? [];

        if ($page < $totalPages) {
            $nextParams = array_merge($currentParams, ['page' => $page + 1]);
            $links['next'] = $baseUrl . '/api/application/servers/' . $serverId . '/databases?' . http_build_query($nextParams);
        }
        if ($page > 1) {
            $prevParams = array_merge($currentParams, ['page' => $page - 1]);
            $links['previous'] = $baseUrl . '/api/application/servers/' . $serverId . '/databases?' . http_build_query($prevParams);
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

    #[OA\Get(
        path: '/api/application/servers/{serverId}/databases/{databaseId}',
        summary: 'Get server database details',
        description: 'Retrieve details for a specific database belonging to a server',
        tags: ['Plugin - Pterodactyl API - Databases'],
        parameters: [
            new OA\Parameter(
                name: 'serverId',
                description: 'The UUID or ID of the server',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'databaseId',
                description: 'The ID of the database',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'include',
                description: 'Comma-separated list of relationships to include (password, host)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['password', 'host', 'password,host'])
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Database details retrieved successfully',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'object', type: 'string', example: 'server_database'),
                        new OA\Property(
                            property: 'attributes',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'server', type: 'integer'),
                                new OA\Property(property: 'host', type: 'integer'),
                                new OA\Property(property: 'database', type: 'string'),
                                new OA\Property(property: 'username', type: 'string'),
                                new OA\Property(property: 'remote', type: 'string'),
                                new OA\Property(property: 'max_connections', type: 'integer'),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Server or database not found'),
            new OA\Response(response: 500, description: 'Internal server error'),
        ]
    )]
    public function show(Request $request, $serverId, $databaseId): Response
    {
        // Get the server
        $server = Server::getServerByUuid($serverId);
        if (!$server) {
            // Try by ID if UUID fails
            $server = Server::getServerById($serverId);
        }

        if (!$server) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'NotFoundHttpException',
                        'status' => '404',
                        'detail' => 'The requested resource does not exist on this server.',
                    ],
                ],
            ], 404);
        }

        // Get the database with details
        $database = ServerDatabase::getServerDatabaseWithDetails($databaseId);
        if (!$database) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'NotFoundHttpException',
                        'status' => '404',
                        'detail' => 'The requested resource does not exist on this server.',
                    ],
                ],
            ], 404);
        }

        // Verify the database belongs to the server
        if ($database['server_id'] != $server['id']) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'NotFoundHttpException',
                        'status' => '404',
                        'detail' => 'The requested resource does not exist on this server.',
                    ],
                ],
            ], 404);
        }

        // Check if relationships should be included
        // Get include parameter - handle both array and string formats
        $allParams = $request->query->all();
        $includeParam = $allParams['include'] ?? '';
        if (is_array($includeParam)) {
            $include = implode(',', $includeParam);
        } else {
            $include = $includeParam;
        }
        $includePassword = strpos($include, 'password') !== false;
        $includeHost = strpos($include, 'host') !== false;

        // Build response data
        $databaseData = [
            'object' => 'server_database',
            'attributes' => [
                'id' => (int) $database['id'],
                'server' => (int) $database['server_id'],
                'host' => (int) $database['database_host_id'],
                'database' => $database['database'],
                'username' => $database['username'],
                'remote' => $database['remote'],
                'max_connections' => (int) $database['max_connections'],
                'created_at' => DateTimePtero::format($database['created_at']),
                'updated_at' => DateTimePtero::format($database['updated_at']),
            ],
        ];

        // Add relationships if requested
        $relationships = [];

        if ($includePassword) {
            $relationships['password'] = [
                'object' => 'database_password',
                'attributes' => [
                    'password' => $database['password'],
                ],
            ];
        }

        if ($includeHost) {
            // Get the full host details
            $host = DatabaseInstance::getDatabaseById($database['database_host_id']);
            if ($host) {
                $relationships['host'] = [
                    'object' => 'database_host',
                    'attributes' => [
                        'id' => (int) $host['id'],
                        'name' => $host['name'],
                        'host' => $host['database_host'],
                        'port' => (int) $host['database_port'],
                        'username' => $host['database_username'],
                        'node' => (int) $host['node_id'],
                        'created_at' => DateTimePtero::format($host['created_at'] ?? '2024-01-01 00:00:00'),
                        'updated_at' => DateTimePtero::format($host['updated_at'] ?? '2024-01-01 00:00:00'),
                    ],
                ];
            }
        }

        if (!empty($relationships)) {
            $databaseData['attributes']['relationships'] = $relationships;
        }

        return ApiResponse::sendManualResponse($databaseData, 200);
    }

    /**
     * Create Server Database - Create a new database for a server.
     */
    #[OA\Post(
        path: '/api/application/servers/{serverId}/databases',
        summary: 'Create a new server database',
        description: 'Create a new database for a specific server',
        tags: ['Plugin - Pterodactyl API - Databases'],
        parameters: [
            new OA\Parameter(
                name: 'serverId',
                description: 'The UUID or ID of the server',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['database', 'remote', 'host'],
                properties: [
                    new OA\Property(property: 'database', type: 'string', description: 'Database name'),
                    new OA\Property(property: 'remote', type: 'string', description: 'Remote connection string'),
                    new OA\Property(property: 'host', type: 'integer', description: 'Database host ID'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Database created successfully',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'object', type: 'string', example: 'server_database'),
                        new OA\Property(
                            property: 'attributes',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'server', type: 'integer'),
                                new OA\Property(property: 'host', type: 'integer'),
                                new OA\Property(property: 'database', type: 'string'),
                                new OA\Property(property: 'username', type: 'string'),
                                new OA\Property(property: 'remote', type: 'string'),
                                new OA\Property(property: 'max_connections', type: 'integer'),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid request data'),
            new OA\Response(response: 404, description: 'Server not found'),
            new OA\Response(response: 500, description: 'Internal server error'),
        ]
    )]
    public function store(Request $request, $serverId): Response
    {
        // Get the server
        $server = Server::getServerByUuid($serverId);
        if (!$server) {
            // Try by ID if UUID fails
            $server = Server::getServerById($serverId);
        }

        if (!$server) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'NotFoundHttpException',
                        'status' => '404',
                        'detail' => 'The requested resource does not exist on this server.',
                    ],
                ],
            ], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                    ],
                ],
            ], 422);
        }

        // Validate required fields
        $requiredFields = ['database', 'remote', 'host'];
        $missingFields = [];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                        'meta' => [
                            'source_field' => implode(', ', $missingFields),
                        ],
                    ],
                ],
            ], 422);
        }

        // Validate host exists
        $host = DatabaseInstance::getDatabaseById($data['host']);
        if (!$host) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                        'meta' => [
                            'source_field' => 'host',
                        ],
                    ],
                ],
            ], 422);
        }

        // Generate database name with server prefix
        $databaseName = 's' . $server['id'] . '_' . $data['database'];
        $username = 'u' . $server['id'] . '_' . substr(md5(uniqid()), 0, 8);

        // Generate password
        $password = bin2hex(random_bytes(16));

        // Prepare server database data
        $serverDatabaseData = [
            'server_id' => $server['id'],
            'database_host_id' => $data['host'],
            'database' => $databaseName,
            'username' => $username,
            'password' => $password,
            'remote' => $data['remote'],
            'max_connections' => 50, // Default value
        ];

        // Create the server database
        $databaseId = ServerDatabase::createServerDatabase($serverDatabaseData);
        if (!$databaseId) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'InternalErrorException',
                        'status' => '500',
                        'detail' => 'An error occurred while processing this request.',
                    ],
                ],
            ], 500);
        }

        // Get the created database
        $createdDatabase = ServerDatabase::getServerDatabaseWithDetails($databaseId);
        if (!$createdDatabase) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'InternalErrorException',
                        'status' => '500',
                        'detail' => 'An error occurred while processing this request.',
                    ],
                ],
            ], 500);
        }

        // Format response
        $responseData = [
            'object' => 'server_database',
            'attributes' => [
                'id' => (int) $createdDatabase['id'],
                'server' => (int) $createdDatabase['server_id'],
                'host' => (int) $createdDatabase['database_host_id'],
                'database' => $createdDatabase['database'],
                'username' => $createdDatabase['username'],
                'remote' => $createdDatabase['remote'],
                'max_connections' => (int) $createdDatabase['max_connections'],
                'created_at' => DateTimePtero::format($createdDatabase['created_at']),
                'updated_at' => DateTimePtero::format($createdDatabase['updated_at']),
                'relationships' => [
                    'password' => [
                        'object' => 'database_password',
                        'attributes' => [
                            'password' => $createdDatabase['password'],
                        ],
                    ],
                ],
            ],
        ];

        return ApiResponse::sendManualResponse($responseData, 201);
    }

    /**
     * Update Database - Update database configuration including remote connection settings.
     */
    #[OA\Patch(
        path: '/api/application/servers/{serverId}/databases/{databaseId}',
        summary: 'Update server database',
        description: 'Update an existing database for a specific server',
        tags: ['Plugin - Pterodactyl API - Databases'],
        parameters: [
            new OA\Parameter(
                name: 'serverId',
                description: 'The UUID or ID of the server',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'databaseId',
                description: 'The ID of the database',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'database', type: 'string', description: 'Database name'),
                    new OA\Property(property: 'remote', type: 'string', description: 'Remote connection string'),
                    new OA\Property(property: 'host', type: 'integer', description: 'Database host ID'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Database updated successfully',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'object', type: 'string', example: 'server_database'),
                        new OA\Property(
                            property: 'attributes',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'server', type: 'integer'),
                                new OA\Property(property: 'host', type: 'integer'),
                                new OA\Property(property: 'database', type: 'string'),
                                new OA\Property(property: 'username', type: 'string'),
                                new OA\Property(property: 'remote', type: 'string'),
                                new OA\Property(property: 'max_connections', type: 'integer'),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid request data'),
            new OA\Response(response: 404, description: 'Server or database not found'),
            new OA\Response(response: 500, description: 'Internal server error'),
        ]
    )]
    public function update(Request $request, $serverId, $databaseId): Response
    {
        // Get the server
        $server = Server::getServerByUuid($serverId);
        if (!$server) {
            // Try by ID if UUID fails
            $server = Server::getServerById($serverId);
        }

        if (!$server) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'NotFoundHttpException',
                        'status' => '404',
                        'detail' => 'The requested resource does not exist on this server.',
                    ],
                ],
            ], 404);
        }

        // Get the database
        $database = ServerDatabase::getServerDatabaseById($databaseId);
        if (!$database) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'NotFoundHttpException',
                        'status' => '404',
                        'detail' => 'The requested resource does not exist on this server.',
                    ],
                ],
            ], 404);
        }

        // Verify the database belongs to the server
        if ($database['server_id'] != $server['id']) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'NotFoundHttpException',
                        'status' => '404',
                        'detail' => 'The requested resource does not exist on this server.',
                    ],
                ],
            ], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                    ],
                ],
            ], 422);
        }

        // Prepare update data
        $updateData = [];
        if (isset($data['remote'])) {
            $updateData['remote'] = $data['remote'];
        }

        if (empty($updateData)) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                    ],
                ],
            ], 422);
        }

        // Update the database
        $success = ServerDatabase::updateServerDatabase($databaseId, $updateData);
        if (!$success) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'InternalErrorException',
                        'status' => '500',
                        'detail' => 'An error occurred while processing this request.',
                    ],
                ],
            ], 500);
        }

        // Get the updated database
        $updatedDatabase = ServerDatabase::getServerDatabaseWithDetails($databaseId);
        if (!$updatedDatabase) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'InternalErrorException',
                        'status' => '500',
                        'detail' => 'An error occurred while processing this request.',
                    ],
                ],
            ], 500);
        }

        // Format response
        $responseData = [
            'object' => 'server_database',
            'attributes' => [
                'id' => (int) $updatedDatabase['id'],
                'server' => (int) $updatedDatabase['server_id'],
                'host' => (int) $updatedDatabase['database_host_id'],
                'database' => $updatedDatabase['database'],
                'username' => $updatedDatabase['username'],
                'remote' => $updatedDatabase['remote'],
                'max_connections' => (int) $updatedDatabase['max_connections'],
                'created_at' => DateTimePtero::format($updatedDatabase['created_at']),
                'updated_at' => DateTimePtero::format($updatedDatabase['updated_at']),
            ],
        ];

        return ApiResponse::sendManualResponse($responseData, 200);
    }

    /**
     * Reset Database Password - Reset the password for a server database.
     */
    public function resetPassword(Request $request, $serverId, $databaseId): Response
    {
        // Get the server
        $server = Server::getServerByUuid($serverId);
        if (!$server) {
            // Try by ID if UUID fails
            $server = Server::getServerById($serverId);
        }

        if (!$server) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'NotFoundHttpException',
                        'status' => '404',
                        'detail' => 'The requested resource does not exist on this server.',
                    ],
                ],
            ], 404);
        }

        // Get the database
        $database = ServerDatabase::getServerDatabaseById($databaseId);
        if (!$database) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'NotFoundHttpException',
                        'status' => '404',
                        'detail' => 'The requested resource does not exist on this server.',
                    ],
                ],
            ], 404);
        }

        // Verify the database belongs to the server
        if ($database['server_id'] != $server['id']) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'NotFoundHttpException',
                        'status' => '404',
                        'detail' => 'The requested resource does not exist on this server.',
                    ],
                ],
            ], 404);
        }

        // Generate new password
        $newPassword = bin2hex(random_bytes(16));

        // Update the database password
        $success = ServerDatabase::updateServerDatabase($databaseId, [
            'password' => $newPassword,
        ]);

        if (!$success) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'InternalErrorException',
                        'status' => '500',
                        'detail' => 'An error occurred while processing this request.',
                    ],
                ],
            ], 500);
        }

        // Get the updated database
        $updatedDatabase = ServerDatabase::getServerDatabaseWithDetails($databaseId);
        if (!$updatedDatabase) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'InternalErrorException',
                        'status' => '500',
                        'detail' => 'An error occurred while processing this request.',
                    ],
                ],
            ], 500);
        }

        // Format response
        $responseData = [
            'object' => 'server_database',
            'attributes' => [
                'id' => (int) $updatedDatabase['id'],
                'server' => (int) $updatedDatabase['server_id'],
                'host' => (int) $updatedDatabase['database_host_id'],
                'database' => $updatedDatabase['database'],
                'username' => $updatedDatabase['username'],
                'remote' => $updatedDatabase['remote'],
                'max_connections' => (int) $updatedDatabase['max_connections'],
                'created_at' => DateTimePtero::format($updatedDatabase['created_at']),
                'updated_at' => DateTimePtero::format($updatedDatabase['updated_at']),
                'relationships' => [
                    'password' => [
                        'object' => 'database_password',
                        'attributes' => [
                            'password' => $newPassword,
                        ],
                    ],
                ],
            ],
        ];

        return ApiResponse::sendManualResponse($responseData, 200);
    }

    /**
     * Delete Server Database - Delete a database from a server.
     */
    #[OA\Delete(
        path: '/api/application/servers/{serverId}/databases/{databaseId}',
        summary: 'Delete server database',
        description: 'Delete a database from a specific server',
        tags: ['Plugin - Pterodactyl API - Databases'],
        parameters: [
            new OA\Parameter(
                name: 'serverId',
                description: 'The UUID or ID of the server',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'databaseId',
                description: 'The ID of the database',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Database deleted successfully'),
            new OA\Response(response: 404, description: 'Server or database not found'),
            new OA\Response(response: 500, description: 'Internal server error'),
        ]
    )]
    public function destroy(Request $request, $serverId, $databaseId): Response
    {
        // Get the server
        $server = Server::getServerByUuid($serverId);
        if (!$server) {
            // Try by ID if UUID fails
            $server = Server::getServerById($serverId);
        }

        if (!$server) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'NotFoundHttpException',
                        'status' => '404',
                        'detail' => 'The requested resource does not exist on this server.',
                    ],
                ],
            ], 404);
        }

        // Get the database
        $database = ServerDatabase::getServerDatabaseById($databaseId);
        if (!$database) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'NotFoundHttpException',
                        'status' => '404',
                        'detail' => 'The requested resource does not exist on this server.',
                    ],
                ],
            ], 404);
        }

        // Verify the database belongs to the server
        if ($database['server_id'] != $server['id']) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'NotFoundHttpException',
                        'status' => '404',
                        'detail' => 'The requested resource does not exist on this server.',
                    ],
                ],
            ], 404);
        }

        // Delete the database
        $success = ServerDatabase::deleteServerDatabase($databaseId);
        if (!$success) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'InternalErrorException',
                        'status' => '500',
                        'detail' => 'An error occurred while processing this request.',
                    ],
                ],
            ], 500);
        }

        // Return 204 No Content on successful deletion
        return ApiResponse::sendManualResponse([], 204);
    }
}
