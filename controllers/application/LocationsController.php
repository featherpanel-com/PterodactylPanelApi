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
use App\Chat\Server;
use App\Chat\Location;
use App\Helpers\ApiResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Addons\pterodactylpanelapi\utils\DateTimePtero;

#[OA\Tag(name: 'Plugin - Pterodactyl API - Locations', description: 'Location management endpoints for Pterodactyl Panel API plugin')]
class LocationsController
{
    // Table names
    public const SERVERS_TABLE = 'featherpanel_servers';
    public const SERVER_VARIABLES_TABLE = 'featherpanel_server_variables';
    public const SPELL_VARIABLES_TABLE = 'featherpanel_spell_variables';

    #[OA\Get(
        path: '/api/application/locations',
        summary: 'List all locations',
        description: 'Retrieve a paginated list of all locations with optional filtering',
        tags: ['Plugin - Pterodactyl API - Locations'],
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
                name: 'filter[short]',
                description: 'Filter by location short code',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'filter[long]',
                description: 'Filter by location long name',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'include',
                description: 'Comma-separated list of relationships to include (nodes, servers)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['nodes', 'servers'])
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of locations retrieved successfully',
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
                                    new OA\Property(property: 'object', type: 'string', example: 'location'),
                                    new OA\Property(
                                        property: 'attributes',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer'),
                                            new OA\Property(property: 'short', type: 'string'),
                                            new OA\Property(property: 'long', type: 'string'),
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

        // Get filter parameters
        $shortFilter = $request->query->get('filter[short]', '');
        $longFilter = $request->query->get('filter[long]', '');

        // Get sort parameter
        $sort = $request->query->get('sort', 'id');

        // Get include parameter
        $include = $request->query->get('include', '');
        $includeNodes = strpos($include, 'nodes') !== false;
        $includeServers = strpos($include, 'servers') !== false;

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

        // Build search criteria
        $search = '';
        if (!empty($shortFilter) || !empty($longFilter)) {
            $searchParts = [];
            if (!empty($shortFilter)) {
                $searchParts[] = $shortFilter;
            }
            if (!empty($longFilter)) {
                $searchParts[] = $longFilter;
            }
            $search = implode(' ', $searchParts);
        }

        // Get locations
        $locations = Location::getAll($search, $perPage, $offset);
        $total = Location::getCount($search);

        // Build response data
        $data = [];
        foreach ($locations as $location) {
            $locationData = [
                'object' => 'location',
                'attributes' => [
                    'id' => (int) $location['id'],
                    'short' => $location['name'], // Using name as short code
                    'long' => $location['description'] ?? null, // Using description as long name
                    'created_at' => DateTimePtero::format($location['created_at']),
                    'updated_at' => DateTimePtero::format($location['updated_at']),
                ],
            ];

            // Add relationships if requested
            if ($includeNodes || $includeServers) {
                $relationships = [];

                if ($includeNodes) {
                    // Get nodes for this location
                    $nodes = Node::getNodesByLocationId($location['id']);
                    $nodeData = [];
                    foreach ($nodes as $node) {
                        $nodeData[] = [
                            'object' => 'node',
                            'attributes' => [
                                'id' => (int) $node['id'],
                                'uuid' => $node['uuid'],
                                'public' => (bool) $node['public'],
                                'name' => $node['name'],
                                'description' => $node['description'],
                                'location_id' => (int) $node['location_id'],
                                'fqdn' => $node['fqdn'],
                                'scheme' => $node['scheme'] ?? 'https',
                                'behind_proxy' => (bool) $node['behind_proxy'],
                                'maintenance_mode' => (bool) $node['maintenance_mode'],
                                'memory' => (int) $node['memory'],
                                'memory_overallocate' => (int) $node['memory_overallocate'],
                                'disk' => (int) $node['disk'],
                                'disk_overallocate' => (int) $node['disk_overallocate'],
                                'upload_size' => (int) $node['upload_size'],
                                'daemon_listen' => (int) $node['daemonListen'],
                                'daemon_sftp' => (int) $node['daemonSFTP'],
                                'daemon_base' => $node['daemonBase'],
                                'created_at' => DateTimePtero::format($node['created_at']),
                                'updated_at' => DateTimePtero::format($node['updated_at']),
                                'allocated_resources' => [
                                    'memory' => (int) ($node['allocated_memory'] ?? 0),
                                    'disk' => (int) ($node['allocated_disk'] ?? 0),
                                ],
                            ],
                        ];
                    }
                    $relationships['nodes'] = [
                        'object' => 'list',
                        'data' => $nodeData,
                    ];
                }

                if ($includeServers) {
                    // Get servers for this location (through nodes) with full server data
                    $servers = [];
                    if ($includeNodes) {
                        foreach ($nodes as $node) {
                            $nodeServers = Server::getServersByNodeId($node['id']);
                            foreach ($nodeServers as $server) {
                                $servers[] = $this->formatServerResponse($server);
                            }
                        }
                    }
                    $relationships['servers'] = [
                        'object' => 'list',
                        'data' => $servers,
                    ];
                }

                $locationData['attributes']['relationships'] = $relationships;
            }

            $data[] = $locationData;
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
     * Get Location Details - Retrieve detailed information about a specific location.
     */
    #[OA\Get(
        path: '/api/application/locations/{locationId}',
        summary: 'Get location details',
        description: 'Retrieve detailed information about a specific location',
        tags: ['Plugin - Pterodactyl API - Locations'],
        parameters: [
            new OA\Parameter(
                name: 'locationId',
                description: 'The ID of the location',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'include',
                description: 'Comma-separated list of relationships to include (nodes, servers)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['nodes', 'servers'])
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Location details retrieved successfully',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'object', type: 'string', example: 'location'),
                        new OA\Property(
                            property: 'attributes',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'short', type: 'string'),
                                new OA\Property(property: 'long', type: 'string'),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Location not found'),
            new OA\Response(response: 500, description: 'Internal server error'),
        ]
    )]
    public function show(Request $request, $locationId): Response
    {
        $include = $request->query->get('include', '');
        $includeNodes = strpos($include, 'nodes') !== false;
        $includeServers = strpos($include, 'servers') !== false;

        // Get location by ID
        $location = Location::getById($locationId);
        if (!$location) {
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

        // Build response data
        $locationData = [
            'object' => 'location',
            'attributes' => [
                'id' => (int) $location['id'],
                'short' => $location['name'], // Using name as short code
                'long' => $location['description'] ?? null, // Using description as long name
                'created_at' => DateTimePtero::format($location['created_at']),
                'updated_at' => DateTimePtero::format($location['updated_at']),
            ],
        ];

        // Add relationships if requested
        if ($includeNodes || $includeServers) {
            $relationships = [];

            if ($includeNodes) {
                // Get nodes for this location
                $nodes = Node::getNodesByLocationId($location['id']);
                $nodeData = [];
                foreach ($nodes as $node) {
                    $nodeData[] = [
                        'object' => 'node',
                        'attributes' => [
                            'id' => (int) $node['id'],
                            'uuid' => $node['uuid'],
                            'public' => (bool) $node['public'],
                            'name' => $node['name'],
                            'description' => $node['description'],
                            'location_id' => (int) $node['location_id'],
                            'fqdn' => $node['fqdn'],
                            'scheme' => $node['scheme'] ?? 'https',
                            'behind_proxy' => (bool) $node['behind_proxy'],
                            'maintenance_mode' => (bool) $node['maintenance_mode'],
                            'memory' => (int) $node['memory'],
                            'memory_overallocate' => (int) $node['memory_overallocate'],
                            'disk' => (int) $node['disk'],
                            'disk_overallocate' => (int) $node['disk_overallocate'],
                            'upload_size' => (int) $node['upload_size'],
                            'daemon_listen' => (int) $node['daemonListen'],
                            'daemon_sftp' => (int) $node['daemonSFTP'],
                            'daemon_base' => $node['daemonBase'],
                            'created_at' => DateTimePtero::format($node['created_at']),
                            'updated_at' => DateTimePtero::format($node['updated_at']),
                            'allocated_resources' => [
                                'memory' => (int) ($node['allocated_memory'] ?? 0),
                                'disk' => (int) ($node['allocated_disk'] ?? 0),
                            ],
                        ],
                    ];
                }
                $relationships['nodes'] = [
                    'object' => 'list',
                    'data' => $nodeData,
                ];
            }

            if ($includeServers) {
                // Get servers for this location (through nodes) with full server data
                $servers = [];
                if ($includeNodes) {
                    foreach ($nodes as $node) {
                        $nodeServers = Server::getServersByNodeId($node['id']);
                        foreach ($nodeServers as $server) {
                            $servers[] = $this->formatServerResponse($server);
                        }
                    }
                } else {
                    // If only servers are requested, get nodes first
                    $nodes = Node::getNodesByLocationId($location['id']);
                    foreach ($nodes as $node) {
                        $nodeServers = Server::getServersByNodeId($node['id']);
                        foreach ($nodeServers as $server) {
                            $servers[] = $this->formatServerResponse($server);
                        }
                    }
                }
                $relationships['servers'] = [
                    'object' => 'list',
                    'data' => $servers,
                ];
            }

            $locationData['attributes']['relationships'] = $relationships;
        }

        return ApiResponse::sendManualResponse($locationData, 200);
    }

    /**
     * Create New Location - Create a new location in the panel.
     */
    #[OA\Post(
        path: '/api/application/locations',
        summary: 'Create a new location',
        description: 'Create a new location with the specified configuration',
        tags: ['Plugin - Pterodactyl API - Locations'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['short', 'long'],
                properties: [
                    new OA\Property(property: 'short', type: 'string', description: 'Location short code'),
                    new OA\Property(property: 'long', type: 'string', description: 'Location long name'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Location created successfully',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'object', type: 'string', example: 'location'),
                        new OA\Property(
                            property: 'attributes',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'short', type: 'string'),
                                new OA\Property(property: 'long', type: 'string'),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid request data'),
            new OA\Response(response: 422, description: 'Validation failed'),
            new OA\Response(response: 500, description: 'Internal server error'),
        ]
    )]
    public function store(Request $request): Response
    {
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
        if (!isset($data['short']) || empty(trim($data['short']))) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                        'meta' => [
                            'source_field' => 'short',
                        ],
                    ],
                ],
            ], 422);
        }

        // Validate field lengths
        if (strlen($data['short']) < 3 || strlen($data['short']) > 60) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                        'meta' => [
                            'source_field' => 'short',
                        ],
                    ],
                ],
            ], 422);
        }

        if (isset($data['long']) && strlen($data['long']) > 191) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                        'meta' => [
                            'source_field' => 'long',
                        ],
                    ],
                ],
            ], 422);
        }

        // Check if location with same name already exists
        $existingLocation = Location::getByName($data['short']);
        if ($existingLocation) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                        'meta' => [
                            'source_field' => 'short',
                        ],
                    ],
                ],
            ], 422);
        }

        // Prepare location data
        $locationData = [
            'name' => $data['short'],
            'description' => $data['long'] ?? null,
        ];

        // Create location in database
        $locationId = Location::create($locationData);
        if (!$locationId) {
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

        // Get the created location
        $createdLocation = Location::getById($locationId);
        if (!$createdLocation) {
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
            'object' => 'location',
            'attributes' => [
                'id' => (int) $createdLocation['id'],
                'short' => $createdLocation['name'],
                'long' => $createdLocation['description'],
                'created_at' => DateTimePtero::format($createdLocation['created_at']),
                'updated_at' => DateTimePtero::format($createdLocation['updated_at']),
            ],
        ];

        return ApiResponse::sendManualResponse($responseData, 201);
    }

    /**
     * Update Location - Update an existing location's information.
     */
    #[OA\Patch(
        path: '/api/application/locations/{locationId}',
        summary: 'Update location',
        description: 'Update an existing location',
        tags: ['Plugin - Pterodactyl API - Locations'],
        parameters: [
            new OA\Parameter(
                name: 'locationId',
                description: 'The ID of the location',
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
                    new OA\Property(property: 'short', type: 'string', description: 'Location short code'),
                    new OA\Property(property: 'long', type: 'string', description: 'Location long name'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Location updated successfully',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'object', type: 'string', example: 'location'),
                        new OA\Property(
                            property: 'attributes',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'short', type: 'string'),
                                new OA\Property(property: 'long', type: 'string'),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid request data'),
            new OA\Response(response: 404, description: 'Location not found'),
            new OA\Response(response: 422, description: 'Validation failed'),
            new OA\Response(response: 500, description: 'Internal server error'),
        ]
    )]
    public function update(Request $request, $locationId): Response
    {
        $location = Location::getById($locationId);
        if (!$location) {
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

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                        'meta' => [
                            'source_field' => 'request_body',
                        ],
                    ],
                ],
            ], 422);
        }

        // Prepare update data
        $updateData = [];

        if (isset($data['short'])) {
            if (empty(trim($data['short']))) {
                return ApiResponse::sendManualResponse([
                    'errors' => [
                        [
                            'code' => 'ValidationException',
                            'status' => '422',
                            'detail' => 'The request data was invalid or malformed.',
                            'meta' => [
                                'source_field' => 'short',
                            ],
                        ],
                    ],
                ], 422);
            }

            if (strlen($data['short']) < 3 || strlen($data['short']) > 60) {
                return ApiResponse::sendManualResponse([
                    'errors' => [
                        [
                            'code' => 'ValidationException',
                            'status' => '422',
                            'detail' => 'The request data was invalid or malformed.',
                            'meta' => [
                                'source_field' => 'short',
                            ],
                        ],
                    ],
                ], 422);
            }

            // Check if location with same name already exists (excluding current location)
            $existingLocation = Location::getByName($data['short']);
            if ($existingLocation && $existingLocation['id'] != $locationId) {
                return ApiResponse::sendManualResponse([
                    'errors' => [
                        [
                            'code' => 'ValidationException',
                            'status' => '422',
                            'detail' => 'The request data was invalid or malformed.',
                            'meta' => [
                                'source_field' => 'short',
                            ],
                        ],
                    ],
                ], 422);
            }

            $updateData['name'] = $data['short'];
        }

        if (isset($data['long'])) {
            if (strlen($data['long']) > 191) {
                return ApiResponse::sendManualResponse([
                    'errors' => [
                        [
                            'code' => 'ValidationException',
                            'status' => '422',
                            'detail' => 'The request data was invalid or malformed.',
                            'meta' => [
                                'source_field' => 'long',
                            ],
                        ],
                    ],
                ], 422);
            }
            $updateData['description'] = $data['long'];
        }

        if (empty($updateData)) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                        'meta' => [
                            'source_field' => 'request_body',
                        ],
                    ],
                ],
            ], 422);
        }

        // Update location
        $updated = Location::update($locationId, $updateData);
        if (!$updated) {
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

        // Get updated location data
        $updatedLocation = Location::getById($locationId);

        // Format response
        $responseData = [
            'object' => 'location',
            'attributes' => [
                'id' => (int) $updatedLocation['id'],
                'short' => $updatedLocation['name'],
                'long' => $updatedLocation['description'],
                'created_at' => DateTimePtero::format($updatedLocation['created_at']),
                'updated_at' => DateTimePtero::format($updatedLocation['updated_at']),
            ],
        ];

        return ApiResponse::sendManualResponse($responseData, 200);
    }

    /**
     * Delete Location - Delete a location from the panel.
     */
    #[OA\Delete(
        path: '/api/application/locations/{locationId}',
        summary: 'Delete location',
        description: 'Delete a location from the panel',
        tags: ['Plugin - Pterodactyl API - Locations'],
        parameters: [
            new OA\Parameter(
                name: 'locationId',
                description: 'The ID of the location',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Location deleted successfully'),
            new OA\Response(response: 404, description: 'Location not found'),
            new OA\Response(response: 400, description: 'Cannot delete location with nodes'),
            new OA\Response(response: 500, description: 'Internal server error'),
        ]
    )]
    public function destroy(Request $request, $locationId): Response
    {
        $location = Location::getById($locationId);
        if (!$location) {
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

        // Check if location has associated nodes
        $nodes = Node::getNodesByLocationId($locationId);
        if (!empty($nodes)) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'Cannot delete a location that has associated nodes. Move or delete all nodes in the location before attempting to delete it.',
                    ],
                ],
            ], 422);
        }

        // Delete location
        $deleted = Location::delete($locationId);
        if (!$deleted) {
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

        // Return 204 No Content as per Pterodactyl API spec
        return new Response('', 204);
    }

    /**
     * Format server response in Pterodactyl API format.
     */
    private function formatServerResponse(array $server): array
    {
        // Build environment variables
        $environment = $this->buildEnvironmentVariables($server);

        return [
            'object' => 'server',
            'attributes' => [
                'id' => (int) $server['id'],
                'external_id' => $server['external_id'],
                'uuid' => $server['uuid'],
                'identifier' => $server['uuidShort'],
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
                    'environment' => $environment,
                ],
                'created_at' => DateTimePtero::format($server['created_at']),
                'updated_at' => DateTimePtero::format($server['updated_at']),
            ],
        ];
    }

    /**
     * Build environment variables like Pterodactyl does.
     */
    private function buildEnvironmentVariables($server)
    {
        $pdo = App::getInstance(true)->getDatabase()->getPdo();

        // Get server variables
        $variablesSql = 'SELECT 
			sv.variable_value,
			spv.env_variable,
			spv.default_value
		FROM ' . self::SERVER_VARIABLES_TABLE . ' sv
		LEFT JOIN ' . self::SPELL_VARIABLES_TABLE . ' spv ON sv.variable_id = spv.id
		WHERE sv.server_id = :server_id';

        $variablesStmt = $pdo->prepare($variablesSql);
        $variablesStmt->bindValue(':server_id', $server['id'], \PDO::PARAM_INT);
        $variablesStmt->execute();
        $variables = $variablesStmt->fetchAll(\PDO::FETCH_ASSOC);

        // Get default variables for the egg
        $defaultVarsSql = 'SELECT env_variable, default_value 
			FROM ' . self::SPELL_VARIABLES_TABLE . ' 
			WHERE spell_id = :spell_id 
			AND id IN (
				SELECT MAX(id) 
				FROM ' . self::SPELL_VARIABLES_TABLE . ' 
				WHERE spell_id = :spell_id 
				GROUP BY env_variable
			)';
        $defaultStmt = $pdo->prepare($defaultVarsSql);
        $defaultStmt->bindValue(':spell_id', $server['spell_id'], \PDO::PARAM_INT);
        $defaultStmt->execute();
        $defaultVars = $defaultStmt->fetchAll(\PDO::FETCH_ASSOC);

        // Build environment variables
        $environment = [];
        foreach ($defaultVars as $var) {
            if ($var['env_variable'] && $var['default_value'] !== null) {
                $environment[$var['env_variable']] = $var['default_value'];
            }
        }

        foreach ($variables as $variable) {
            if ($variable['env_variable'] && $variable['variable_value'] !== null) {
                $environment[$variable['env_variable']] = $variable['variable_value'];
            }
        }

        // Add Pterodactyl's automatic variables
        $environment['P_SERVER_UUID'] = $server['uuid'];
        $environment['P_SERVER_ALLOCATION_LIMIT'] = (string) ($server['allocation_limit'] ?? 0);

        // Add location info if available
        $node = Node::getNodeById($server['node_id']);
        if ($node && isset($node['location_id'])) {
            $location = Location::getById($node['location_id']);
            if ($location) {
                $environment['P_SERVER_LOCATION'] = $location['name'];
            }
        }

        return $environment;
    }
}
