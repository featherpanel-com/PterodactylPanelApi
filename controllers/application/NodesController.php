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
use App\Chat\Node;
use App\Chat\Server;
use App\Chat\Location;
use App\Chat\Allocation;
use App\Helpers\ApiResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Addons\pterodactylpanelapi\utils\DateTimePtero;

#[OA\Tag(name: 'Plugin - Pterodactyl API - Nodes', description: 'Node management endpoints for Pterodactyl Panel API plugin')]
class NodesController
{
    // Table names
    public const SERVERS_TABLE = 'featherpanel_servers';
    public const SERVER_VARIABLES_TABLE = 'featherpanel_server_variables';
    public const SPELL_VARIABLES_TABLE = 'featherpanel_spell_variables';

    #[OA\Get(
        path: '/api/application/nodes',
        summary: 'List all nodes',
        description: 'Retrieve a paginated list of all nodes with optional filtering',
        tags: ['Plugin - Pterodactyl API - Nodes'],
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
                name: 'filter[name]',
                description: 'Filter by node name',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'filter[uuid]',
                description: 'Filter by node UUID',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'filter[fqdn]',
                description: 'Filter by node FQDN',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'include',
                description: 'Comma-separated list of relationships to include (allocations, location, servers)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['allocations', 'location', 'servers'])
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of nodes retrieved successfully',
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
                                    new OA\Property(property: 'object', type: 'string', example: 'node'),
                                    new OA\Property(
                                        property: 'attributes',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer'),
                                            new OA\Property(property: 'uuid', type: 'string'),
                                            new OA\Property(property: 'public', type: 'boolean'),
                                            new OA\Property(property: 'name', type: 'string'),
                                            new OA\Property(property: 'description', type: 'string'),
                                            new OA\Property(property: 'location_id', type: 'integer'),
                                            new OA\Property(property: 'fqdn', type: 'string'),
                                            new OA\Property(property: 'scheme', type: 'string'),
                                            new OA\Property(property: 'behind_proxy', type: 'boolean'),
                                            new OA\Property(property: 'maintenance_mode', type: 'boolean'),
                                            new OA\Property(property: 'memory', type: 'integer'),
                                            new OA\Property(property: 'memory_overallocate', type: 'integer'),
                                            new OA\Property(property: 'disk', type: 'integer'),
                                            new OA\Property(property: 'disk_overallocate', type: 'integer'),
                                            new OA\Property(property: 'upload_size', type: 'integer'),
                                            new OA\Property(property: 'daemon_listen', type: 'integer'),
                                            new OA\Property(property: 'daemon_sftp', type: 'integer'),
                                            new OA\Property(property: 'daemon_base', type: 'string'),
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

        // Get filter parameters - handle nested query parameters
        $allParams = $request->query->all();
        $filterParams = $allParams['filter'] ?? [];
        if (!is_array($filterParams)) {
            $filterParams = [];
        }
        $nameFilter = $filterParams['name'] ?? '';
        $uuidFilter = $filterParams['uuid'] ?? '';
        $fqdnFilter = $filterParams['fqdn'] ?? '';

        // Get sort parameter
        $sort = $request->query->get('sort', 'id');

        // Get include parameter - handle both array and string formats
        $allParams = $request->query->all();
        $includeParam = $allParams['include'] ?? '';
        if (is_array($includeParam)) {
            $include = implode(',', $includeParam);
        } else {
            $include = $includeParam;
        }
        $includeAllocations = strpos($include, 'allocations') !== false;
        $includeLocation = strpos($include, 'location') !== false;
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
        if (!empty($nameFilter) || !empty($uuidFilter) || !empty($fqdnFilter)) {
            $searchParts = [];
            if (!empty($nameFilter)) {
                $searchParts[] = $nameFilter;
            }
            if (!empty($uuidFilter)) {
                $searchParts[] = $uuidFilter;
            }
            if (!empty($fqdnFilter)) {
                $searchParts[] = $fqdnFilter;
            }
            $search = implode(' ', $searchParts);
        }

        // Get nodes
        $nodes = Node::searchNodes($page, $perPage, $search, [], $sort, 'ASC');
        $total = Node::getNodesCount($search);

        // Build response data
        $data = [];
        foreach ($nodes as $node) {
            $nodeData = [
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

            // Add relationships if requested
            if ($includeAllocations || $includeLocation || $includeServers) {
                $relationships = [];

                if ($includeLocation) {
                    // Get location for this node
                    $location = Location::getById($node['location_id']);
                    if ($location) {
                        $relationships['location'] = [
                            'object' => 'location',
                            'attributes' => [
                                'id' => (int) $location['id'],
                                'short' => $location['name'],
                                'long' => $location['description'],
                                'created_at' => DateTimePtero::format($location['created_at']),
                                'updated_at' => DateTimePtero::format($location['updated_at']),
                            ],
                        ];
                    }
                }

                if ($includeAllocations) {
                    // Get allocations for this node
                    $allocations = Allocation::getAll(null, $node['id'], null, 1000, 0);
                    $allocationData = [];
                    foreach ($allocations as $allocation) {
                        $allocationData[] = [
                            'object' => 'allocation',
                            'attributes' => [
                                'id' => (int) $allocation['id'],
                                'ip' => $allocation['ip'],
                                'alias' => $allocation['ip_alias'],
                                'port' => (int) $allocation['port'],
                                'notes' => $allocation['notes'],
                                'assigned' => (bool) $allocation['server_id'],
                            ],
                        ];
                    }
                    $relationships['allocations'] = [
                        'object' => 'list',
                        'data' => $allocationData,
                    ];
                }

                if ($includeServers) {
                    // Get servers for this node with full server data
                    $nodeServers = Server::getServersByNodeId($node['id']);
                    $servers = [];
                    foreach ($nodeServers as $server) {
                        $servers[] = $this->formatServerResponse($server);
                    }
                    $relationships['servers'] = [
                        'object' => 'list',
                        'data' => $servers,
                    ];
                }

                $nodeData['attributes']['relationships'] = $relationships;
            }

            $data[] = $nodeData;
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
     * Get Node Details - Retrieve detailed information about a specific node.
     */
    #[OA\Get(
        path: '/api/application/nodes/{nodeId}',
        summary: 'Get node details',
        description: 'Retrieve detailed information about a specific node',
        tags: ['Plugin - Pterodactyl API - Nodes'],
        parameters: [
            new OA\Parameter(
                name: 'nodeId',
                description: 'The ID of the node',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'include',
                description: 'Comma-separated list of relationships to include (allocations, location, servers)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['allocations', 'location', 'servers'])
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Node details retrieved successfully',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'object', type: 'string', example: 'node'),
                        new OA\Property(
                            property: 'attributes',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'uuid', type: 'string'),
                                new OA\Property(property: 'public', type: 'boolean'),
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'description', type: 'string'),
                                new OA\Property(property: 'location_id', type: 'integer'),
                                new OA\Property(property: 'fqdn', type: 'string'),
                                new OA\Property(property: 'scheme', type: 'string'),
                                new OA\Property(property: 'behind_proxy', type: 'boolean'),
                                new OA\Property(property: 'maintenance_mode', type: 'boolean'),
                                new OA\Property(property: 'memory', type: 'integer'),
                                new OA\Property(property: 'memory_overallocate', type: 'integer'),
                                new OA\Property(property: 'disk', type: 'integer'),
                                new OA\Property(property: 'disk_overallocate', type: 'integer'),
                                new OA\Property(property: 'upload_size', type: 'integer'),
                                new OA\Property(property: 'daemon_listen', type: 'integer'),
                                new OA\Property(property: 'daemon_sftp', type: 'integer'),
                                new OA\Property(property: 'daemon_base', type: 'string'),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Node not found'),
            new OA\Response(response: 500, description: 'Internal server error'),
        ]
    )]
    public function show(Request $request, $nodeId): Response
    {
        // Get include parameter - handle both array and string formats
        $allParams = $request->query->all();
        $includeParam = $allParams['include'] ?? '';
        if (is_array($includeParam)) {
            $include = implode(',', $includeParam);
        } else {
            $include = $includeParam;
        }
        $includeAllocations = strpos($include, 'allocations') !== false;
        $includeLocation = strpos($include, 'location') !== false;
        $includeServers = strpos($include, 'servers') !== false;

        // Get node by ID
        $node = Node::getNodeById($nodeId);
        if (!$node) {
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
        $nodeData = [
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

        // Add relationships if requested
        if ($includeAllocations || $includeLocation || $includeServers) {
            $relationships = [];

            if ($includeLocation) {
                // Get location for this node
                $location = Location::getById($node['location_id']);
                if ($location) {
                    $relationships['location'] = [
                        'object' => 'location',
                        'attributes' => [
                            'id' => (int) $location['id'],
                            'short' => $location['name'],
                            'long' => $location['description'],
                            'created_at' => DateTimePtero::format($location['created_at']),
                            'updated_at' => DateTimePtero::format($location['updated_at']),
                        ],
                    ];
                }
            }

            if ($includeAllocations) {
                // Get allocations for this node
                $allocations = Allocation::getAll(null, $node['id'], null, 1000, 0);
                $allocationData = [];
                foreach ($allocations as $allocation) {
                    $allocationData[] = [
                        'object' => 'allocation',
                        'attributes' => [
                            'id' => (int) $allocation['id'],
                            'ip' => $allocation['ip'],
                            'alias' => $allocation['ip_alias'],
                            'port' => (int) $allocation['port'],
                            'notes' => $allocation['notes'],
                            'assigned' => (bool) $allocation['server_id'],
                        ],
                    ];
                }
                $relationships['allocations'] = [
                    'object' => 'list',
                    'data' => $allocationData,
                ];
            }

            if ($includeServers) {
                // Get servers for this node with full server data
                $nodeServers = Server::getServersByNodeId($node['id']);
                $servers = [];
                foreach ($nodeServers as $server) {
                    $servers[] = $this->formatServerResponse($server);
                }
                $relationships['servers'] = [
                    'object' => 'list',
                    'data' => $servers,
                ];
            }

            $nodeData['attributes']['relationships'] = $relationships;
        }

        return ApiResponse::sendManualResponse($nodeData, 200);
    }

    /**
     * Get Deployable Nodes - Retrieve nodes available for server deployment.
     */
    #[OA\Get(
        path: '/api/application/nodes/deployable',
        summary: 'List deployable nodes',
        description: 'Retrieve a list of nodes that are available for server deployment',
        tags: ['Plugin - Pterodactyl API - Nodes'],
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
                description: 'Comma-separated list of relationships to include (allocations, location)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['allocations', 'location'])
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of deployable nodes retrieved successfully',
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
                                    new OA\Property(property: 'object', type: 'string', example: 'node'),
                                    new OA\Property(
                                        property: 'attributes',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer'),
                                            new OA\Property(property: 'uuid', type: 'string'),
                                            new OA\Property(property: 'public', type: 'boolean'),
                                            new OA\Property(property: 'name', type: 'string'),
                                            new OA\Property(property: 'description', type: 'string'),
                                            new OA\Property(property: 'location_id', type: 'integer'),
                                            new OA\Property(property: 'fqdn', type: 'string'),
                                            new OA\Property(property: 'scheme', type: 'string'),
                                            new OA\Property(property: 'behind_proxy', type: 'boolean'),
                                            new OA\Property(property: 'maintenance_mode', type: 'boolean'),
                                            new OA\Property(property: 'memory', type: 'integer'),
                                            new OA\Property(property: 'memory_overallocate', type: 'integer'),
                                            new OA\Property(property: 'disk', type: 'integer'),
                                            new OA\Property(property: 'disk_overallocate', type: 'integer'),
                                            new OA\Property(property: 'upload_size', type: 'integer'),
                                            new OA\Property(property: 'daemon_listen', type: 'integer'),
                                            new OA\Property(property: 'daemon_sftp', type: 'integer'),
                                            new OA\Property(property: 'daemon_base', type: 'string'),
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
    public function deployable(Request $request): Response
    {
        // Get pagination parameters
        $page = (int) $request->query->get('page', 1);
        $perPage = (int) $request->query->get('per_page', 50);

        // Get resource requirements
        $requiredMemory = (int) $request->query->get('memory', 0);
        $requiredDisk = (int) $request->query->get('disk', 0);

        // Get include parameter - handle both array and string formats
        $allParams = $request->query->all();
        $includeParam = $allParams['include'] ?? '';
        $includeAllocations = false;

        if (is_array($includeParam)) {
            // Handle array format: include[]=allocations or include=allocations&include=something
            $includeAllocations = in_array('allocations', $includeParam, true);
        } elseif (is_string($includeParam)) {
            // Handle comma-separated string: include=allocations,node
            $includeAllocations = strpos($includeParam, 'allocations') !== false;
        }

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

        // Get all nodes first
        $allNodes = Node::getAllNodes();
        $deployableNodes = [];

        // Filter nodes based on resource availability
        foreach ($allNodes as $node) {
            // Skip nodes in maintenance mode
            if ($node['maintenance_mode']) {
                continue;
            }

            // Calculate available resources
            $totalMemory = $node['memory'];
            $totalDisk = $node['disk'];
            $allocatedMemory = (int) ($node['allocated_memory'] ?? 0);
            $allocatedDisk = (int) ($node['allocated_disk'] ?? 0);

            // Calculate available memory (considering overallocation)
            $memoryOverallocate = (int) $node['memory_overallocate'];
            $maxMemory = $totalMemory + ($totalMemory * $memoryOverallocate / 100);
            $availableMemory = $maxMemory - $allocatedMemory;

            // Calculate available disk (considering overallocation)
            $diskOverallocate = (int) $node['disk_overallocate'];
            $maxDisk = $totalDisk + ($totalDisk * $diskOverallocate / 100);
            $availableDisk = $maxDisk - $allocatedDisk;

            // Check if node has enough resources
            $hasEnoughMemory = ($requiredMemory == 0) || ($availableMemory >= $requiredMemory);
            $hasEnoughDisk = ($requiredDisk == 0) || ($availableDisk >= $requiredDisk);

            if ($hasEnoughMemory && $hasEnoughDisk) {
                $deployableNodes[] = $node;
            }
        }

        // Apply pagination to filtered results
        $total = count($deployableNodes);
        $paginatedNodes = array_slice($deployableNodes, $offset, $perPage);

        // Build response data
        $data = [];
        foreach ($paginatedNodes as $node) {
            $nodeData = [
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

            // Add relationships if allocations are requested
            if ($includeAllocations) {
                $allocations = Allocation::getAll(null, (int) $node['id'], null, 1000, 0);
                $allocationData = [];
                foreach ($allocations as $allocation) {
                    $allocationData[] = [
                        'object' => 'allocation',
                        'attributes' => [
                            'id' => (int) $allocation['id'],
                            'ip' => $allocation['ip'],
                            'alias' => $allocation['ip_alias'],
                            'port' => (int) $allocation['port'],
                            'notes' => $allocation['notes'],
                            'assigned' => (bool) $allocation['server_id'],
                        ],
                    ];
                }
                $nodeData['attributes']['relationships'] = [
                    'allocations' => [
                        'object' => 'list',
                        'data' => $allocationData,
                    ],
                ];
            }

            $data[] = $nodeData;
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
     * Create New Node - Create a new Wings daemon node in the panel.
     */
    #[OA\Post(
        path: '/api/application/nodes',
        summary: 'Create a new node',
        description: 'Create a new node with the specified configuration',
        tags: ['Plugin - Pterodactyl API - Nodes'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['name', 'location_id', 'fqdn', 'scheme', 'memory', 'disk', 'daemon_listen', 'daemon_sftp', 'daemon_base'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', description: 'Node name'),
                    new OA\Property(property: 'description', type: 'string', description: 'Node description'),
                    new OA\Property(property: 'location_id', type: 'integer', description: 'Location ID'),
                    new OA\Property(property: 'fqdn', type: 'string', description: 'Node FQDN'),
                    new OA\Property(property: 'scheme', type: 'string', enum: ['http', 'https'], description: 'Connection scheme'),
                    new OA\Property(property: 'public', type: 'boolean', description: 'Whether the node is public'),
                    new OA\Property(property: 'behind_proxy', type: 'boolean', description: 'Whether the node is behind a proxy'),
                    new OA\Property(property: 'maintenance_mode', type: 'boolean', description: 'Whether the node is in maintenance mode'),
                    new OA\Property(property: 'memory', type: 'integer', description: 'Total memory in MB'),
                    new OA\Property(property: 'memory_overallocate', type: 'integer', description: 'Memory overallocation percentage'),
                    new OA\Property(property: 'disk', type: 'integer', description: 'Total disk space in MB'),
                    new OA\Property(property: 'disk_overallocate', type: 'integer', description: 'Disk overallocation percentage'),
                    new OA\Property(property: 'upload_size', type: 'integer', description: 'Maximum upload size in MB'),
                    new OA\Property(property: 'daemon_listen', type: 'integer', description: 'Daemon listen port'),
                    new OA\Property(property: 'daemon_sftp', type: 'integer', description: 'Daemon SFTP port'),
                    new OA\Property(property: 'daemon_base', type: 'string', description: 'Daemon base directory'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Node created successfully',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'object', type: 'string', example: 'node'),
                        new OA\Property(
                            property: 'attributes',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'uuid', type: 'string'),
                                new OA\Property(property: 'public', type: 'boolean'),
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'description', type: 'string'),
                                new OA\Property(property: 'location_id', type: 'integer'),
                                new OA\Property(property: 'fqdn', type: 'string'),
                                new OA\Property(property: 'scheme', type: 'string'),
                                new OA\Property(property: 'behind_proxy', type: 'boolean'),
                                new OA\Property(property: 'maintenance_mode', type: 'boolean'),
                                new OA\Property(property: 'memory', type: 'integer'),
                                new OA\Property(property: 'memory_overallocate', type: 'integer'),
                                new OA\Property(property: 'disk', type: 'integer'),
                                new OA\Property(property: 'disk_overallocate', type: 'integer'),
                                new OA\Property(property: 'upload_size', type: 'integer'),
                                new OA\Property(property: 'daemon_listen', type: 'integer'),
                                new OA\Property(property: 'daemon_sftp', type: 'integer'),
                                new OA\Property(property: 'daemon_base', type: 'string'),
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
        $requiredFields = ['name', 'location_id', 'fqdn', 'memory', 'disk'];
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

        // Validate location exists
        $location = Location::getById($data['location_id']);
        if (!$location) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                        'meta' => [
                            'source_field' => 'location_id',
                        ],
                    ],
                ],
            ], 422);
        }

        // Validate resource values
        if ($data['memory'] < 128) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                        'meta' => [
                            'source_field' => 'memory',
                        ],
                    ],
                ],
            ], 422);
        }

        if ($data['disk'] < 1024) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                        'meta' => [
                            'source_field' => 'disk',
                        ],
                    ],
                ],
            ], 422);
        }

        // Validate port numbers
        if (isset($data['daemon_listen']) && ($data['daemon_listen'] < 1 || $data['daemon_listen'] > 65535)) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                        'meta' => [
                            'source_field' => 'daemon_listen',
                        ],
                    ],
                ],
            ], 422);
        }

        if (isset($data['daemon_sftp']) && ($data['daemon_sftp'] < 1 || $data['daemon_sftp'] > 65535)) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                        'meta' => [
                            'source_field' => 'daemon_sftp',
                        ],
                    ],
                ],
            ], 422);
        }

        // Validate overallocation percentages
        if (isset($data['memory_overallocate']) && ($data['memory_overallocate'] < 0 || $data['memory_overallocate'] > 1000)) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                        'meta' => [
                            'source_field' => 'memory_overallocate',
                        ],
                    ],
                ],
            ], 422);
        }

        if (isset($data['disk_overallocate']) && ($data['disk_overallocate'] < 0 || $data['disk_overallocate'] > 1000)) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                        'meta' => [
                            'source_field' => 'disk_overallocate',
                        ],
                    ],
                ],
            ], 422);
        }

        // Check if node with same name already exists
        $existingNode = Node::getNodeByName($data['name']);
        if ($existingNode) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                        'meta' => [
                            'source_field' => 'name',
                        ],
                    ],
                ],
            ], 422);
        }

        // Check if FQDN is already in use
        $existingFqdn = Node::getNodeByFqdn($data['fqdn']);
        if ($existingFqdn) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                        'meta' => [
                            'source_field' => 'fqdn',
                        ],
                    ],
                ],
            ], 422);
        }

        // Prepare node data
        $nodeData = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'location_id' => $data['location_id'],
            'fqdn' => $data['fqdn'],
            'scheme' => $data['scheme'] ?? 'https',
            'behind_proxy' => isset($data['behind_proxy']) ? (int) $data['behind_proxy'] : 0,
            'public' => isset($data['public']) ? (int) $data['public'] : 1,
            'daemon_base' => $data['daemon_base'] ?? '/var/lib/pterodactyl/volumes',
            'daemon_sftp' => $data['daemon_sftp'] ?? 2022,
            'daemon_listen' => $data['daemon_listen'] ?? 8080,
            'memory' => $data['memory'],
            'daemon_token_id' => Node::generateDaemonTokenId(),
            'daemon_token' => Node::generateDaemonToken(),
            'memory_overallocate' => $data['memory_overallocate'] ?? 0,
            'disk' => $data['disk'],
            'disk_overallocate' => $data['disk_overallocate'] ?? 0,
            'upload_size' => $data['upload_size'] ?? 100,
            'maintenance_mode' => isset($data['maintenance_mode']) ? (int) $data['maintenance_mode'] : 0,
        ];

        // Generate UUID
        $nodeData['uuid'] = \App\Helpers\UUIDUtils::generateV4();

        // Generate daemon token
        $nodeData['daemon_token'] = Node::generateDaemonToken();

        // Create node in database
        $nodeId = Node::create($nodeData);
        if (!$nodeId) {
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

        // Get the created node
        $createdNode = Node::getNodeById($nodeId);
        if (!$createdNode) {
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
            'object' => 'node',
            'attributes' => [
                'id' => (int) $createdNode['id'],
                'uuid' => $createdNode['uuid'],
                'public' => (bool) $createdNode['public'],
                'name' => $createdNode['name'],
                'description' => $createdNode['description'],
                'location_id' => (int) $createdNode['location_id'],
                'fqdn' => $createdNode['fqdn'],
                'scheme' => $createdNode['scheme'],
                'behind_proxy' => (bool) $createdNode['behind_proxy'],
                'maintenance_mode' => (bool) $createdNode['maintenance_mode'],
                'memory' => (int) $createdNode['memory'],
                'memory_overallocate' => (int) $createdNode['memory_overallocate'],
                'disk' => (int) $createdNode['disk'],
                'disk_overallocate' => (int) $createdNode['disk_overallocate'],
                'upload_size' => (int) $createdNode['upload_size'],
                'daemon_listen' => (int) $createdNode['daemonListen'],
                'daemon_sftp' => (int) $createdNode['daemonSFTP'],
                'daemon_base' => $createdNode['daemonBase'],
                'created_at' => DateTimePtero::format($createdNode['created_at']),
                'updated_at' => DateTimePtero::format($createdNode['updated_at']),
                'allocated_resources' => [
                    'memory' => 0,
                    'disk' => 0,
                ],
            ],
        ];

        return ApiResponse::sendManualResponse($responseData, 201);
    }

    /**
     * Update Node Configuration - Update an existing node's configuration.
     */
    #[OA\Patch(
        path: '/api/application/nodes/{nodeId}',
        summary: 'Update node',
        description: 'Update an existing node configuration',
        tags: ['Plugin - Pterodactyl API - Nodes'],
        parameters: [
            new OA\Parameter(
                name: 'nodeId',
                description: 'The ID of the node',
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
                    new OA\Property(property: 'name', type: 'string', description: 'Node name'),
                    new OA\Property(property: 'description', type: 'string', description: 'Node description'),
                    new OA\Property(property: 'location_id', type: 'integer', description: 'Location ID'),
                    new OA\Property(property: 'fqdn', type: 'string', description: 'Node FQDN'),
                    new OA\Property(property: 'scheme', type: 'string', enum: ['http', 'https'], description: 'Connection scheme'),
                    new OA\Property(property: 'public', type: 'boolean', description: 'Whether the node is public'),
                    new OA\Property(property: 'behind_proxy', type: 'boolean', description: 'Whether the node is behind a proxy'),
                    new OA\Property(property: 'maintenance_mode', type: 'boolean', description: 'Whether the node is in maintenance mode'),
                    new OA\Property(property: 'memory', type: 'integer', description: 'Total memory in MB'),
                    new OA\Property(property: 'memory_overallocate', type: 'integer', description: 'Memory overallocation percentage'),
                    new OA\Property(property: 'disk', type: 'integer', description: 'Total disk space in MB'),
                    new OA\Property(property: 'disk_overallocate', type: 'integer', description: 'Disk overallocation percentage'),
                    new OA\Property(property: 'upload_size', type: 'integer', description: 'Maximum upload size in MB'),
                    new OA\Property(property: 'daemon_listen', type: 'integer', description: 'Daemon listen port'),
                    new OA\Property(property: 'daemon_sftp', type: 'integer', description: 'Daemon SFTP port'),
                    new OA\Property(property: 'daemon_base', type: 'string', description: 'Daemon base directory'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Node updated successfully',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'object', type: 'string', example: 'node'),
                        new OA\Property(
                            property: 'attributes',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'uuid', type: 'string'),
                                new OA\Property(property: 'public', type: 'boolean'),
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'description', type: 'string'),
                                new OA\Property(property: 'location_id', type: 'integer'),
                                new OA\Property(property: 'fqdn', type: 'string'),
                                new OA\Property(property: 'scheme', type: 'string'),
                                new OA\Property(property: 'behind_proxy', type: 'boolean'),
                                new OA\Property(property: 'maintenance_mode', type: 'boolean'),
                                new OA\Property(property: 'memory', type: 'integer'),
                                new OA\Property(property: 'memory_overallocate', type: 'integer'),
                                new OA\Property(property: 'disk', type: 'integer'),
                                new OA\Property(property: 'disk_overallocate', type: 'integer'),
                                new OA\Property(property: 'upload_size', type: 'integer'),
                                new OA\Property(property: 'daemon_listen', type: 'integer'),
                                new OA\Property(property: 'daemon_sftp', type: 'integer'),
                                new OA\Property(property: 'daemon_base', type: 'string'),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid request data'),
            new OA\Response(response: 404, description: 'Node not found'),
            new OA\Response(response: 422, description: 'Validation failed'),
            new OA\Response(response: 500, description: 'Internal server error'),
        ]
    )]
    public function update(Request $request, $nodeId): Response
    {
        // Get the node
        $node = Node::getNodeById($nodeId);
        if (!$node) {
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

        // If no data provided, return the current node
        if (empty($data)) {
            $nodeData = $this->formatNodeResponse($node, $request);

            return ApiResponse::sendManualResponse($nodeData, 200);
        }

        // Validate location exists if provided
        if (isset($data['location_id'])) {
            $location = Location::getById($data['location_id']);
            if (!$location) {
                return ApiResponse::sendManualResponse([
                    'errors' => [
                        [
                            'code' => 'ValidationException',
                            'status' => '422',
                            'detail' => 'The request data was invalid or malformed.',
                            'meta' => [
                                'source_field' => 'location_id',
                            ],
                        ],
                    ],
                ], 422);
            }
        }

        // Validate resource values if provided
        if (isset($data['memory']) && $data['memory'] < 128) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                        'meta' => [
                            'source_field' => 'memory',
                        ],
                    ],
                ],
            ], 422);
        }

        if (isset($data['disk']) && $data['disk'] < 1024) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                        'meta' => [
                            'source_field' => 'disk',
                        ],
                    ],
                ],
            ], 422);
        }

        // Validate port numbers if provided
        if (isset($data['daemon_listen']) && ($data['daemon_listen'] < 1 || $data['daemon_listen'] > 65535)) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                        'meta' => [
                            'source_field' => 'daemon_listen',
                        ],
                    ],
                ],
            ], 422);
        }

        if (isset($data['daemon_sftp']) && ($data['daemon_sftp'] < 1 || $data['daemon_sftp'] > 65535)) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                        'meta' => [
                            'source_field' => 'daemon_sftp',
                        ],
                    ],
                ],
            ], 422);
        }

        // Validate overallocation percentages if provided
        if (isset($data['memory_overallocate']) && ($data['memory_overallocate'] < 0 || $data['memory_overallocate'] > 1000)) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                        'meta' => [
                            'source_field' => 'memory_overallocate',
                        ],
                    ],
                ],
            ], 422);
        }

        if (isset($data['disk_overallocate']) && ($data['disk_overallocate'] < 0 || $data['disk_overallocate'] > 1000)) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                        'meta' => [
                            'source_field' => 'disk_overallocate',
                        ],
                    ],
                ],
            ], 422);
        }

        // Check for name conflicts if name is being changed
        if (isset($data['name']) && $data['name'] !== $node['name']) {
            $existingNode = Node::getNodeByName($data['name']);
            if ($existingNode && $existingNode['id'] != $nodeId) {
                return ApiResponse::sendManualResponse([
                    'errors' => [
                        [
                            'code' => 'ValidationException',
                            'status' => '422',
                            'detail' => 'The request data was invalid or malformed.',
                            'meta' => [
                                'source_field' => 'name',
                            ],
                        ],
                    ],
                ], 422);
            }
        }

        // Check for FQDN conflicts if FQDN is being changed
        if (isset($data['fqdn']) && $data['fqdn'] !== $node['fqdn']) {
            $existingFqdn = Node::getNodeByFqdn($data['fqdn']);
            if ($existingFqdn && $existingFqdn['id'] != $nodeId) {
                return ApiResponse::sendManualResponse([
                    'errors' => [
                        [
                            'code' => 'ValidationException',
                            'status' => '422',
                            'detail' => 'The request data was invalid or malformed.',
                            'meta' => [
                                'source_field' => 'fqdn',
                            ],
                        ],
                    ],
                ], 422);
            }
        }

        // Prepare update data - only include fields that are provided
        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }
        if (isset($data['location_id'])) {
            $updateData['location_id'] = $data['location_id'];
        }
        if (isset($data['fqdn'])) {
            $updateData['fqdn'] = $data['fqdn'];
        }
        if (isset($data['scheme'])) {
            $updateData['scheme'] = $data['scheme'];
        }
        if (isset($data['behind_proxy'])) {
            $updateData['behind_proxy'] = (int) $data['behind_proxy'];
        }
        if (isset($data['public'])) {
            $updateData['public'] = (int) $data['public'];
        }
        if (isset($data['daemon_base'])) {
            $updateData['daemon_base'] = $data['daemon_base'];
        }
        if (isset($data['daemon_sftp'])) {
            $updateData['daemon_sftp'] = $data['daemon_sftp'];
        }
        if (isset($data['daemon_listen'])) {
            $updateData['daemon_listen'] = $data['daemon_listen'];
        }
        if (isset($data['memory'])) {
            $updateData['memory'] = $data['memory'];
        }
        if (isset($data['memory_overallocate'])) {
            $updateData['memory_overallocate'] = $data['memory_overallocate'];
        }
        if (isset($data['disk'])) {
            $updateData['disk'] = $data['disk'];
        }
        if (isset($data['disk_overallocate'])) {
            $updateData['disk_overallocate'] = $data['disk_overallocate'];
        }
        if (isset($data['upload_size'])) {
            $updateData['upload_size'] = $data['upload_size'];
        }
        if (isset($data['maintenance_mode'])) {
            $updateData['maintenance_mode'] = (int) $data['maintenance_mode'];
        }

        // If no fields to update, return current node
        if (empty($updateData)) {
            $nodeData = $this->formatNodeResponse($node, $request);

            return ApiResponse::sendManualResponse($nodeData, 200);
        }

        // Update the node
        $success = Node::updateNodeById($nodeId, $updateData);
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

        // Get the updated node
        $updatedNode = Node::getNodeById($nodeId);
        if (!$updatedNode) {
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
        $nodeData = $this->formatNodeResponse($updatedNode, $request);

        return ApiResponse::sendManualResponse($nodeData, 200);
    }

    /**
     * Get Node Configuration - Get the Wings daemon configuration for a node.
     */
    #[OA\Get(
        path: '/api/application/nodes/{nodeId}/configuration',
        summary: 'Get node configuration',
        description: 'Retrieve the Wings daemon configuration for a specific node',
        tags: ['Plugin - Pterodactyl API - Nodes'],
        parameters: [
            new OA\Parameter(
                name: 'nodeId',
                description: 'The ID of the node',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Node configuration retrieved successfully',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'object', type: 'string', example: 'node'),
                        new OA\Property(
                            property: 'attributes',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'uuid', type: 'string'),
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'description', type: 'string'),
                                new OA\Property(property: 'location_id', type: 'integer'),
                                new OA\Property(property: 'fqdn', type: 'string'),
                                new OA\Property(property: 'scheme', type: 'string'),
                                new OA\Property(property: 'behind_proxy', type: 'boolean'),
                                new OA\Property(property: 'maintenance_mode', type: 'boolean'),
                                new OA\Property(property: 'memory', type: 'integer'),
                                new OA\Property(property: 'memory_overallocate', type: 'integer'),
                                new OA\Property(property: 'disk', type: 'integer'),
                                new OA\Property(property: 'disk_overallocate', type: 'integer'),
                                new OA\Property(property: 'upload_size', type: 'integer'),
                                new OA\Property(property: 'daemon_listen', type: 'integer'),
                                new OA\Property(property: 'daemon_sftp', type: 'integer'),
                                new OA\Property(property: 'daemon_base', type: 'string'),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Node not found'),
            new OA\Response(response: 500, description: 'Internal server error'),
        ]
    )]
    public function configuration(Request $request, $nodeId): Response
    {
        // Get the node
        $node = Node::getNodeById($nodeId);
        if (!$node) {
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

        // Get the panel configuration for the remote URL
        $panelUrl = $request->getSchemeAndHttpHost();

        // Build the Wings configuration
        $configuration = [
            'debug' => false, // You might want to make this configurable
            'uuid' => $node['uuid'],
            'token_id' => $node['daemon_token_id'] ?? 'unknown',
            'token' => $node['daemon_token'] ?? 'unknown',
            'api' => [
                'host' => '0.0.0.0',
                'port' => (int) $node['daemonListen'],
                'ssl' => [
                    'enabled' => $node['scheme'] === 'https',
                    'cert' => '/etc/ssl/certs/wings.crt',
                    'key' => '/etc/ssl/private/wings.key',
                ],
                'upload_limit' => (int) $node['upload_size'],
            ],
            'system' => [
                'data' => $node['daemonBase'],
                'sftp' => [
                    'bind_port' => (int) $node['daemonSFTP'],
                ],
            ],
            'allowed_mounts' => [], // This could be made configurable
            'remote' => $panelUrl,
        ];

        // Add SSL configuration if the node uses HTTPS
        if ($node['scheme'] === 'https') {
            $configuration['api']['ssl'] = [
                'enabled' => true,
                'cert' => '/etc/ssl/certs/wings.crt',
                'key' => '/etc/ssl/private/wings.key',
            ];
        } else {
            $configuration['api']['ssl'] = [
                'enabled' => false,
            ];
        }

        // Add proxy configuration if the node is behind a proxy
        if ($node['behind_proxy']) {
            $configuration['api']['trusted_proxies'] = [
                '10.0.0.0/8',
                '172.16.0.0/12',
                '192.168.0.0/16',
            ];
        }

        return ApiResponse::sendManualResponse($configuration, 200);
    }

    /**
     * List Node Allocations - Get all allocations for a specific node.
     */
    #[OA\Get(
        path: '/api/application/nodes/{nodeId}/allocations',
        summary: 'List node allocations',
        description: 'Retrieve all IP allocations for a specific node',
        tags: ['Plugin - Pterodactyl API - Nodes'],
        parameters: [
            new OA\Parameter(
                name: 'nodeId',
                description: 'The ID of the node',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
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
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Node allocations retrieved successfully',
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
                                    new OA\Property(property: 'object', type: 'string', example: 'allocation'),
                                    new OA\Property(
                                        property: 'attributes',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer'),
                                            new OA\Property(property: 'ip', type: 'string'),
                                            new OA\Property(property: 'port', type: 'integer'),
                                            new OA\Property(property: 'alias', type: 'string'),
                                            new OA\Property(property: 'port_alias', type: 'string'),
                                            new OA\Property(property: 'assigned', type: 'boolean'),
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
            new OA\Response(response: 404, description: 'Node not found'),
            new OA\Response(response: 500, description: 'Internal server error'),
        ]
    )]
    public function allocations(Request $request, $nodeId): Response
    {
        // Get the node
        $node = Node::getNodeById($nodeId);
        if (!$node) {
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

        // Get allocations for this node
        $allocations = Allocation::getAll(null, $nodeId, null, $perPage, $offset);

        // Get total count for pagination
        $total = Allocation::getCount(null, $nodeId, null);

        // Build response data
        $data = [];
        foreach ($allocations as $allocation) {
            $data[] = [
                'object' => 'allocation',
                'attributes' => [
                    'id' => (int) $allocation['id'],
                    'ip' => $allocation['ip'],
                    'ip_alias' => $allocation['ip_alias'],
                    'port' => (int) $allocation['port'],
                    'notes' => $allocation['notes'],
                    'assigned' => (bool) $allocation['server_id'],
                ],
            ];
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
     * Create Node Allocations - Create new allocations for a node.
     */
    #[OA\Post(
        path: '/api/application/nodes/{nodeId}/allocations',
        summary: 'Create node allocations',
        description: 'Create new IP allocations for a specific node',
        tags: ['Plugin - Pterodactyl API - Nodes'],
        parameters: [
            new OA\Parameter(
                name: 'nodeId',
                description: 'The ID of the node',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['ip', 'ports'],
                properties: [
                    new OA\Property(property: 'ip', type: 'string', description: 'IP address'),
                    new OA\Property(property: 'ports', type: 'array', items: new OA\Items(type: 'string'), description: 'Array of ports to allocate'),
                    new OA\Property(property: 'alias', type: 'string', description: 'IP alias'),
                    new OA\Property(property: 'port_alias', type: 'string', description: 'Port alias'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Allocations created successfully',
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
                                    new OA\Property(property: 'object', type: 'string', example: 'allocation'),
                                    new OA\Property(
                                        property: 'attributes',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer'),
                                            new OA\Property(property: 'ip', type: 'string'),
                                            new OA\Property(property: 'port', type: 'integer'),
                                            new OA\Property(property: 'alias', type: 'string'),
                                            new OA\Property(property: 'port_alias', type: 'string'),
                                            new OA\Property(property: 'assigned', type: 'boolean'),
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
            new OA\Response(response: 400, description: 'Invalid request data'),
            new OA\Response(response: 404, description: 'Node not found'),
            new OA\Response(response: 422, description: 'Validation failed'),
            new OA\Response(response: 500, description: 'Internal server error'),
        ]
    )]
    public function createAllocations(Request $request, $nodeId): Response
    {
        // Get the node
        $node = Node::getNodeById($nodeId);
        if (!$node) {
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
        if (!isset($data['ip']) || empty($data['ip'])) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                        'meta' => [
                            'source_field' => 'ip',
                        ],
                    ],
                ],
            ], 422);
        }

        if (!isset($data['ports']) || !is_array($data['ports']) || empty($data['ports'])) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                        'meta' => [
                            'source_field' => 'ports',
                        ],
                    ],
                ],
            ], 422);
        }

        // Validate IP format
        if (!filter_var($data['ip'], FILTER_VALIDATE_IP)) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                        'meta' => [
                            'source_field' => 'ip',
                        ],
                    ],
                ],
            ], 422);
        }

        // Parse ports and port ranges
        $ports = [];
        foreach ($data['ports'] as $portString) {
            if (strpos($portString, '-') !== false) {
                // Port range (e.g., "25568-25570")
                $range = explode('-', $portString);
                if (count($range) !== 2) {
                    return ApiResponse::sendManualResponse([
                        'errors' => [
                            [
                                'code' => 'ValidationException',
                                'status' => '422',
                                'detail' => 'The request data was invalid or malformed.',
                                'meta' => [
                                    'source_field' => 'ports',
                                ],
                            ],
                        ],
                    ], 422);
                }

                $start = (int) trim($range[0]);
                $end = (int) trim($range[1]);

                if ($start < 1 || $start > 65535 || $end < 1 || $end > 65535 || $start > $end) {
                    return ApiResponse::sendManualResponse([
                        'errors' => [
                            [
                                'code' => 'ValidationException',
                                'status' => '422',
                                'detail' => 'The request data was invalid or malformed.',
                                'meta' => [
                                    'source_field' => 'ports',
                                ],
                            ],
                        ],
                    ], 422);
                }

                // Add all ports in range
                for ($port = $start; $port <= $end; ++$port) {
                    $ports[] = $port;
                }
            } else {
                // Single port
                $port = (int) $portString;
                if ($port < 1 || $port > 65535) {
                    return ApiResponse::sendManualResponse([
                        'errors' => [
                            [
                                'code' => 'ValidationException',
                                'status' => '422',
                                'detail' => 'The request data was invalid or malformed.',
                                'meta' => [
                                    'source_field' => 'ports',
                                ],
                            ],
                        ],
                    ], 422);
                }
                $ports[] = $port;
            }
        }

        // Remove duplicates and sort
        $ports = array_unique($ports);
        sort($ports);

        // Check for existing allocations
        $conflicts = [];
        foreach ($ports as $port) {
            if (!Allocation::isUniqueIpPort($nodeId, $data['ip'], $port)) {
                $conflicts[] = $port;
            }
        }

        if (!empty($conflicts)) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'The request data was invalid or malformed.',
                        'meta' => [
                            'source_field' => 'ports',
                            'detail' => 'Port(s) already allocated: ' . implode(', ', $conflicts),
                        ],
                    ],
                ],
            ], 422);
        }

        // Prepare allocation data
        $allocationsToCreate = [];
        foreach ($ports as $port) {
            $allocationsToCreate[] = [
                'node_id' => $nodeId,
                'ip' => $data['ip'],
                'ip_alias' => $data['ip_alias'] ?? null,
                'port' => $port,
                'notes' => null,
                'server_id' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }

        // Create allocations in batch
        $createdIds = Allocation::createBatch($allocationsToCreate);
        if (!$createdIds) {
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

        // Fetch the created allocations with full data
        $createdAllocations = [];
        foreach ($createdIds as $id) {
            $allocation = Allocation::getById($id);
            if ($allocation) {
                $createdAllocations[] = $allocation;
            }
        }

        // Format response data
        $data = [];
        foreach ($createdAllocations as $allocation) {
            $data[] = [
                'object' => 'allocation',
                'attributes' => [
                    'id' => (int) $allocation['id'],
                    'ip' => $allocation['ip'],
                    'ip_alias' => $allocation['ip_alias'],
                    'port' => (int) $allocation['port'],
                    'notes' => $allocation['notes'],
                    'assigned' => (bool) $allocation['server_id'],
                ],
            ];
        }

        $response = [
            'object' => 'list',
            'data' => $data,
        ];

        return ApiResponse::sendManualResponse($response, 201);
    }

    /**
     * Delete Node Allocation - Delete a specific allocation from a node.
     */
    #[OA\Delete(
        path: '/api/application/nodes/{nodeId}/allocations/{allocationId}',
        summary: 'Delete node allocation',
        description: 'Delete a specific IP allocation from a node',
        tags: ['Plugin - Pterodactyl API - Nodes'],
        parameters: [
            new OA\Parameter(
                name: 'nodeId',
                description: 'The ID of the node',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'allocationId',
                description: 'The ID of the allocation',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Allocation deleted successfully'),
            new OA\Response(response: 404, description: 'Node or allocation not found'),
            new OA\Response(response: 400, description: 'Cannot delete assigned allocation'),
            new OA\Response(response: 500, description: 'Internal server error'),
        ]
    )]
    public function deleteAllocation(Request $request, $nodeId, $allocationId): Response
    {
        // Get the node
        $node = Node::getNodeById($nodeId);
        if (!$node) {
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

        // Get the allocation
        $allocation = Allocation::getById($allocationId);
        if (!$allocation) {
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

        // Verify the allocation belongs to this node
        if ($allocation['node_id'] != $nodeId) {
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

        // Check if allocation is assigned to a server
        if ($allocation['server_id']) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'Cannot delete allocation that is assigned to a server.',
                    ],
                ],
            ], 422);
        }

        // Delete the allocation
        $success = Allocation::delete($allocationId);
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

    /**
     * Delete Node - Delete a node from the panel.
     */
    #[OA\Delete(
        path: '/api/application/nodes/{nodeId}',
        summary: 'Delete node',
        description: 'Delete a node from the panel',
        tags: ['Plugin - Pterodactyl API - Nodes'],
        parameters: [
            new OA\Parameter(
                name: 'nodeId',
                description: 'The ID of the node',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Node deleted successfully'),
            new OA\Response(response: 404, description: 'Node not found'),
            new OA\Response(response: 400, description: 'Cannot delete node with servers'),
            new OA\Response(response: 500, description: 'Internal server error'),
        ]
    )]
    public function destroy(Request $request, $nodeId): Response
    {
        // Get the node
        $node = Node::getNodeById($nodeId);
        if (!$node) {
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

        // Check if node has servers
        $nodeServers = Server::getServersByNodeId($nodeId);
        if (!empty($nodeServers)) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'Cannot delete node that has servers assigned to it.',
                    ],
                ],
            ], 422);
        }

        // Check if node has allocations
        $allocations = Allocation::getAll(null, $nodeId, null, 1, 0);
        if (!empty($allocations)) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'ValidationException',
                        'status' => '422',
                        'detail' => 'Cannot delete node that has allocations. Delete allocations first.',
                    ],
                ],
            ], 422);
        }

        // Delete the node
        $success = Node::hardDeleteNode($nodeId);
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

    /**
     * Format node response in Pterodactyl API format.
     */
    private function formatNodeResponse(array $node, Request $request): array
    {
        // Build basic node data
        $nodeData = [
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

        // Check if relationships should be included
        $allParams = $request->query->all();
        $includeParam = $allParams['include'] ?? '';
        if (is_array($includeParam)) {
            $include = implode(',', $includeParam);
        } else {
            $include = $includeParam;
        }
        if (!empty($include)) {
            $includeAllocations = strpos($include, 'allocations') !== false;
            $includeLocation = strpos($include, 'location') !== false;
            $includeServers = strpos($include, 'servers') !== false;

            $relationships = [];

            if ($includeLocation) {
                // Get location data
                $location = Location::getById($node['location_id']);
                if ($location) {
                    $relationships['location'] = [
                        'object' => 'location',
                        'attributes' => [
                            'id' => (int) $location['id'],
                            'short' => $location['name'],
                            'long' => $location['description'],
                            'created_at' => DateTimePtero::format($location['created_at']),
                            'updated_at' => DateTimePtero::format($location['updated_at']),
                        ],
                    ];
                }
            }

            if ($includeAllocations) {
                // Get allocations for this node
                $allocations = Allocation::getAll(null, $node['id'], null, 1000, 0);
                $allocationData = [];
                foreach ($allocations as $allocation) {
                    $allocationData[] = [
                        'object' => 'allocation',
                        'attributes' => [
                            'id' => (int) $allocation['id'],
                            'ip' => $allocation['ip'],
                            'alias' => $allocation['ip_alias'],
                            'port' => (int) $allocation['port'],
                            'notes' => $allocation['notes'],
                            'assigned' => (bool) $allocation['server_id'],
                        ],
                    ];
                }
                $relationships['allocations'] = [
                    'object' => 'list',
                    'data' => $allocationData,
                ];
            }

            if ($includeServers) {
                // Get servers for this node with full server data
                $nodeServers = Server::getServersByNodeId($node['id']);
                $servers = [];
                foreach ($nodeServers as $server) {
                    $servers[] = $this->formatServerResponse($server);
                }
                $relationships['servers'] = [
                    'object' => 'list',
                    'data' => $servers,
                ];
            }

            $nodeData['attributes']['relationships'] = $relationships;
        }

        return $nodeData;
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
