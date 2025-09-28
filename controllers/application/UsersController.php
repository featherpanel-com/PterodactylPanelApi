<?php

namespace App\Addons\pterodactylpanelapi\controllers\application;

use App\Addons\pterodactylpanelapi\chat\PterodactylApiChat;
use App\Addons\pterodactylpanelapi\utils\DateTimePtero;
use App\App;
use App\Config\ConfigInterface;
use App\Helpers\ApiResponse;
use App\Helpers\PermissionHelper;
use App\Helpers\UUIDUtils;
use App\Chat\User;
use App\Permissions;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[OA\Tag(name: 'Plugin - Pterodactyl API - Users', description: 'User management endpoints for Pterodactyl Panel API plugin')]
class UsersController
{
	public const USERS_TABLE = 'featherpanel_users';
	public const SERVERS_TABLE_VARIABLES = 'featherpanel_server_variables';
	public const SPELLS_TABLE_VARIABLES = 'featherpanel_spell_variables';
	public const SERVERS_TABLE = 'featherpanel_servers';

	#[OA\Get(
		path: '/api/application/users',
		summary: 'List all users',
		description: 'Retrieve a paginated list of all users with optional filtering and sorting',
		tags: ['Plugin - Pterodactyl API - Users'],
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
				name: 'filter[email]',
				description: 'Filter by user email',
				in: 'query',
				required: false,
				schema: new OA\Schema(type: 'string')
			),
			new OA\Parameter(
				name: 'filter[uuid]',
				description: 'Filter by user UUID',
				in: 'query',
				required: false,
				schema: new OA\Schema(type: 'string')
			),
			new OA\Parameter(
				name: 'filter[username]',
				description: 'Filter by username',
				in: 'query',
				required: false,
				schema: new OA\Schema(type: 'string')
			),
			new OA\Parameter(
				name: 'filter[external_id]',
				description: 'Filter by external ID',
				in: 'query',
				required: false,
				schema: new OA\Schema(type: 'string')
			),
			new OA\Parameter(
				name: 'sort',
				description: 'Sort field',
				in: 'query',
				required: false,
				schema: new OA\Schema(type: 'string', enum: ['id', 'uuid', 'username', 'email', 'created_at', 'updated_at'], default: 'id')
			),
			new OA\Parameter(
				name: 'include',
				description: 'Comma-separated list of relationships to include (servers)',
				in: 'query',
				required: false,
				schema: new OA\Schema(type: 'string', enum: ['servers'])
			)
		],
		responses: [
			new OA\Response(
				response: 200,
				description: 'List of users retrieved successfully',
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
									new OA\Property(property: 'object', type: 'string', example: 'user'),
									new OA\Property(
										property: 'attributes',
										type: 'object',
										properties: [
											new OA\Property(property: 'id', type: 'integer'),
											new OA\Property(property: 'external_id', type: 'string'),
											new OA\Property(property: 'uuid', type: 'string'),
											new OA\Property(property: 'username', type: 'string'),
											new OA\Property(property: 'email', type: 'string'),
											new OA\Property(property: 'first_name', type: 'string'),
											new OA\Property(property: 'last_name', type: 'string'),
											new OA\Property(property: 'language', type: 'string'),
											new OA\Property(property: 'admin', type: 'boolean'),
											new OA\Property(property: 'root_admin', type: 'boolean'),
											new OA\Property(property: '2fa', type: 'boolean'),
											new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
											new OA\Property(property: 'updated_at', type: 'string', format: 'date-time')
										]
									)
								]
							)
						),
						new OA\Property(
							property: 'meta',
							type: 'object',
							properties: [
								new OA\Property(property: 'pagination', type: 'object')
							]
						)
					]
				)
			),
			new OA\Response(response: 500, description: 'Internal server error')
		]
	)]
	public function index(Request $request): Response
	{
		$page = (int) $request->query->get('page', 1);
		$perPage = (int) $request->query->get('per_page', 50);
		$perPage = max(1, min($perPage, 100)); // Clamp per_page between 1 and 100

		// Safely get filter parameters
		$filterParams = $request->query->all('filter');
		if (!is_array($filterParams)) {
			$filterParams = [];
		}

		$filters = [
			'email' => $filterParams['email'] ?? null,
			'uuid' => $filterParams['uuid'] ?? null,
			'username' => $filterParams['username'] ?? null,
			'external_id' => $filterParams['external_id'] ?? null,
		];

		$sort = $request->query->get('sort', 'id');
		$allowedSorts = ['id', 'uuid', 'username', 'email', 'created_at', 'updated_at'];
		if (!in_array($sort, $allowedSorts, true)) {
			$sort = 'id';
		}

		// Parse include parameter
		$include = $request->query->get('include', '');
		$includeServers = in_array('servers', explode(',', $include), true);

		$pdo = App::getInstance(true)->getDatabase()->getPdo();

		// Build WHERE clause for filters
		$where = [];
		$params = [];
		foreach ($filters as $key => $value) {
			if ($value !== null && $value !== '') {
				$where[] = "$key = :$key";
				$params[$key] = $value;
			}
		}
		$whereClause = '';
		if (!empty($where)) {
			$whereClause = 'WHERE ' . implode(' AND ', $where);
		}

		// Count total users
		$countSql = "SELECT COUNT(*) FROM " . self::USERS_TABLE . " $whereClause";
		$stmt = $pdo->prepare($countSql);
		$stmt->execute($params);
		$total = (int) $stmt->fetchColumn();

		$totalPages = (int) ceil($total / max(1, $perPage));
		$page = max(1, min($page, $totalPages > 0 ? $totalPages : 1));
		$offset = ($page - 1) * $perPage;

		// Build the main query with conditional JOINs
		if ($includeServers) {
			// Query with JOINs to get users, servers, and variables in one go
			$sql = "SELECT 
						u.*,
						s.id as server_id,
						s.external_id as server_external_id,
						s.uuid as server_uuid,
						s.uuidShort as server_uuidShort,
						s.name as server_name,
						s.description as server_description,
						s.status as server_status,
						s.suspended as server_suspended,
						s.memory as server_memory,
						s.swap as server_swap,
						s.disk as server_disk,
						s.io as server_io,
						s.cpu as server_cpu,
						s.threads as server_threads,
						s.oom_disabled as server_oom_disabled,
						s.database_limit as server_database_limit,
						s.allocation_limit as server_allocation_limit,
						s.backup_limit as server_backup_limit,
						s.owner_id as server_owner_id,
						s.node_id as server_node_id,
						s.allocation_id as server_allocation_id,
						s.realms_id as server_realms_id,
						s.spell_id as server_spell_id,
						s.startup as server_startup,
						s.image as server_image,
						s.installed_at as server_installed_at,
						s.updated_at as server_updated_at,
						s.created_at as server_created_at,
						sv.variable_id,
						sv.variable_value,
						spv.env_variable,
						spv.default_value
					FROM " . self::USERS_TABLE . " u
					LEFT JOIN " . self::SERVERS_TABLE . " s ON u.id = s.owner_id
					LEFT JOIN " . self::SERVERS_TABLE_VARIABLES . " sv ON s.id = sv.server_id
					LEFT JOIN " . self::SPELLS_TABLE_VARIABLES . " spv ON sv.variable_id = spv.id
					$whereClause 
					ORDER BY u.$sort, s.id, sv.variable_id
					LIMIT :limit OFFSET :offset";
		} else {
			// Simple query for users only
			$sql = "SELECT * FROM " . self::USERS_TABLE . " $whereClause ORDER BY $sort LIMIT :limit OFFSET :offset";
		}

		$stmt = $pdo->prepare($sql);
		foreach ($params as $key => $value) {
			$stmt->bindValue(":$key", $value);
		}
		$stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
		$stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
		$stmt->execute();
		$results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		$data = [];
		$userServers = [];

		// Process results and group by user
		foreach ($results as $row) {
			$userId = (int) $row['id'];

			// Initialize user data if not exists
			if (!isset($userServers[$userId])) {
				$userServers[$userId] = [
					'user' => [
						'id' => $userId,
						'external_id' => $row['external_id'] ?? null,
						'uuid' => $row['uuid'],
						'username' => $row['username'],
						'email' => $row['email'],
						'first_name' => $row['first_name'] ?? null,
						'last_name' => $row['last_name'] ?? null,
						'language' => 'en',
						'root_admin' => (bool) (PermissionHelper::hasPermission($row['uuid'], Permissions::ADMIN_ROOT) ?? false),
						'2fa' => $row['two_fa_enabled'],
						'created_at' => DateTimePtero::format($row['first_seen']) ?? DateTimePtero::format($row['first_seen']),
						'updated_at' => DateTimePtero::format($row['last_seen']) ?? DateTimePtero::format($row['last_seen']),
					],
					'servers' => []
				];
			}

			// Add server data if exists and servers are included
			if ($includeServers && $row['server_id']) {
				$serverId = (int) $row['server_id'];

				// Initialize server if not exists
				if (!isset($userServers[$userId]['servers'][$serverId])) {
					$userServers[$userId]['servers'][$serverId] = [
						'id' => $serverId,
						'external_id' => $row['server_external_id'] ?? null,
						'uuid' => $row['server_uuid'],
						'uuidShort' => $row['server_uuidShort'],
						'name' => $row['server_name'],
						'description' => $row['server_description'],
						'status' => $row['server_status'],
						'suspended' => (bool) $row['server_suspended'],
						'memory' => (int) $row['server_memory'],
						'swap' => (int) $row['server_swap'],
						'disk' => (int) $row['server_disk'],
						'io' => (int) $row['server_io'],
						'cpu' => (int) $row['server_cpu'],
						'threads' => $row['server_threads'],
						'oom_disabled' => (bool) $row['server_oom_disabled'],
						'database_limit' => (int) $row['server_database_limit'],
						'allocation_limit' => (int) $row['server_allocation_limit'],
						'backup_limit' => (int) $row['server_backup_limit'],
						'owner_id' => (int) $row['server_owner_id'],
						'node_id' => (int) $row['server_node_id'],
						'allocation_id' => (int) $row['server_allocation_id'],
						'realms_id' => (int) $row['server_realms_id'],
						'spell_id' => (int) $row['server_spell_id'],
						'startup' => $row['server_startup'],
						'image' => $row['server_image'],
						'installed_at' => $row['server_installed_at'],
						'updated_at' => $row['server_updated_at'],
						'created_at' => $row['server_created_at'],
						'environment' => new \stdClass()
					];
				}

				// Add environment variable if exists
				if ($row['variable_id'] && $row['env_variable']) {
					$envKey = $row['env_variable'];
					$envValue = $row['variable_value'] ?? $row['default_value'] ?? '';
					$userServers[$userId]['servers'][$serverId]['environment']->$envKey = $envValue;
				}
			}
		}

		// Build final response data
		foreach ($userServers as $userId => $userData) {
			$responseData = [
				'object' => 'user',
				'attributes' => $userData['user'],
			];

			if ($includeServers && !empty($userData['servers'])) {
				$serverData = [];
				foreach ($userData['servers'] as $server) {
					$serverData[] = [
						'object' => 'server',
						'attributes' => [
							'id' => $server['id'],
							'external_id' => $server['external_id'],
							'uuid' => $server['uuid'],
							'identifier' => $server['uuidShort'],
							'name' => $server['name'],
							'description' => $server['description'],
							'status' => $server['status'],
							'suspended' => $server['suspended'],
							'limits' => [
								'memory' => $server['memory'],
								'swap' => $server['swap'],
								'disk' => $server['disk'],
								'io' => $server['io'],
								'cpu' => $server['cpu'],
								'threads' => $server['threads'],
								'oom_disabled' => $server['oom_disabled'],
							],
							'feature_limits' => [
								'databases' => $server['database_limit'],
								'allocations' => $server['allocation_limit'],
								'backups' => $server['backup_limit'],
							],
							'user' => $server['owner_id'],
							'node' => $server['node_id'],
							'allocation' => $server['allocation_id'],
							'nest' => $server['realms_id'],
							'egg' => $server['spell_id'],
							'container' => [
								'startup_command' => $server['startup'],
								'image' => $server['image'],
								'installed' => $server['installed_at'] ? 1 : 0,
								'environment' => $server['environment'],
							],
							'updated_at' => DateTimePtero::format($server['updated_at']) ?? DateTimePtero::format($server['updated_at']),
							'created_at' => DateTimePtero::format($server['created_at']) ?? DateTimePtero::format($server['created_at']),
						],
					];
				}

				$responseData['attributes']['relationships'] = [
					'servers' => [
						'object' => 'list',
						'data' => $serverData,
					],
				];
			}

			$data[] = $responseData;
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
					'links' => [
						'previous' => App::getInstance(true)->getConfig()->getSetting(ConfigInterface::APP_URL, 'http://localhost') . '/api/application/users?page=' . ($page - 1),
						'next' => App::getInstance(true)->getConfig()->getSetting(ConfigInterface::APP_URL, 'http://localhost') . '/api/application/users?page=' . ($page + 1),
					],
				],
			],
		];

		return ApiResponse::sendManualResponse($response, 200);
	}

	#[OA\Get(
		path: '/api/application/users/{userId}',
		summary: 'Get user details',
		description: 'Retrieve detailed information about a specific user',
		tags: ['Plugin - Pterodactyl API - Users'],
		parameters: [
			new OA\Parameter(
				name: 'userId',
				description: 'The ID of the user',
				in: 'path',
				required: true,
				schema: new OA\Schema(type: 'integer')
			),
			new OA\Parameter(
				name: 'include',
				description: 'Comma-separated list of relationships to include (servers)',
				in: 'query',
				required: false,
				schema: new OA\Schema(type: 'string', enum: ['servers'])
			)
		],
		responses: [
			new OA\Response(
				response: 200,
				description: 'User details retrieved successfully',
				content: new OA\JsonContent(
					type: 'object',
					properties: [
						new OA\Property(property: 'object', type: 'string', example: 'user'),
						new OA\Property(
							property: 'attributes',
							type: 'object',
							properties: [
								new OA\Property(property: 'id', type: 'integer'),
								new OA\Property(property: 'external_id', type: 'string'),
								new OA\Property(property: 'uuid', type: 'string'),
								new OA\Property(property: 'username', type: 'string'),
								new OA\Property(property: 'email', type: 'string'),
								new OA\Property(property: 'first_name', type: 'string'),
								new OA\Property(property: 'last_name', type: 'string'),
								new OA\Property(property: 'language', type: 'string'),
								new OA\Property(property: 'admin', type: 'boolean'),
								new OA\Property(property: 'root_admin', type: 'boolean'),
								new OA\Property(property: '2fa', type: 'boolean'),
								new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
								new OA\Property(property: 'updated_at', type: 'string', format: 'date-time')
							]
						)
					]
				)
			),
			new OA\Response(response: 404, description: 'User not found'),
			new OA\Response(response: 500, description: 'Internal server error')
		]
	)]
	public function show(Request $request, int $userId): Response
	{
		// Parse include parameter
		$include = $request->query->get('include', '');
		$includeServers = in_array('servers', explode(',', $include), true);

		$pdo = App::getInstance(true)->getDatabase()->getPdo();

		// Build the main query with conditional JOINs
		if ($includeServers) {
			// Query with JOINs to get user, servers, and variables in one go
			$sql = "SELECT 
						u.*,
						s.id as server_id,
						s.external_id as server_external_id,
						s.uuid as server_uuid,
						s.uuidShort as server_uuidShort,
						s.name as server_name,
						s.description as server_description,
						s.status as server_status,
						s.suspended as server_suspended,
						s.memory as server_memory,
						s.swap as server_swap,
						s.disk as server_disk,
						s.io as server_io,
						s.cpu as server_cpu,
						s.threads as server_threads,
						s.oom_disabled as server_oom_disabled,
						s.database_limit as server_database_limit,
						s.allocation_limit as server_allocation_limit,
						s.backup_limit as server_backup_limit,
						s.owner_id as server_owner_id,
						s.node_id as server_node_id,
						s.allocation_id as server_allocation_id,
						s.realms_id as server_realms_id,
						s.spell_id as server_spell_id,
						s.startup as server_startup,
						s.image as server_image,
						s.installed_at as server_installed_at,
						s.updated_at as server_updated_at,
						s.created_at as server_created_at,
						sv.variable_id,
						sv.variable_value,
						spv.env_variable,
						spv.default_value
					FROM " . self::USERS_TABLE . " u
					LEFT JOIN " . self::SERVERS_TABLE . " s ON u.id = s.owner_id
					LEFT JOIN " . self::SERVERS_TABLE_VARIABLES . " sv ON s.id = sv.server_id
					LEFT JOIN " . self::SPELLS_TABLE_VARIABLES . " spv ON sv.variable_id = spv.id
					WHERE u.id = :user_id
					ORDER BY s.id, sv.variable_id";
		} else {
			// Simple query for user only
			$sql = "SELECT * FROM " . self::USERS_TABLE . " WHERE id = :user_id";
		}

		$stmt = $pdo->prepare($sql);
		$stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
		$stmt->execute();
		$results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		// Check if user exists
		if (empty($results)) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'NotFoundHttpException',
						'status' => "404",
						'detail' => 'The requested resource could not be found on the server.'
					]
				]
			], 404);
		}

		$user = $results[0];
		$userServers = [];

		// Process results and group by server
		foreach ($results as $row) {
			// Add server data if exists and servers are included
			if ($includeServers && $row['server_id']) {
				$serverId = (int) $row['server_id'];

				// Initialize server if not exists
				if (!isset($userServers[$serverId])) {
					$userServers[$serverId] = [
						'id' => $serverId,
						'external_id' => $row['server_external_id'] ?? null,
						'uuid' => $row['server_uuid'],
						'uuidShort' => $row['server_uuidShort'],
						'name' => $row['server_name'],
						'description' => $row['server_description'],
						'status' => $row['server_status'],
						'suspended' => (bool) $row['server_suspended'],
						'memory' => (int) $row['server_memory'],
						'swap' => (int) $row['server_swap'],
						'disk' => (int) $row['server_disk'],
						'io' => (int) $row['server_io'],
						'cpu' => (int) $row['server_cpu'],
						'threads' => $row['server_threads'],
						'oom_disabled' => (bool) $row['server_oom_disabled'],
						'database_limit' => (int) $row['server_database_limit'],
						'allocation_limit' => (int) $row['server_allocation_limit'],
						'backup_limit' => (int) $row['server_backup_limit'],
						'owner_id' => (int) $row['server_owner_id'],
						'node_id' => (int) $row['server_node_id'],
						'allocation_id' => (int) $row['server_allocation_id'],
						'realms_id' => (int) $row['server_realms_id'],
						'spell_id' => (int) $row['server_spell_id'],
						'startup' => $row['server_startup'],
						'image' => $row['server_image'],
						'installed_at' => $row['server_installed_at'],
						'updated_at' => DateTimePtero::format($row['server_updated_at']) ?? DateTimePtero::format($row['server_updated_at']),
						'created_at' => DateTimePtero::format($row['server_created_at']) ?? DateTimePtero::format($row['server_created_at']),
						'environment' => new \stdClass()
					];
				}

				// Add environment variable if exists
				if ($row['variable_id'] && $row['env_variable']) {
					$envKey = $row['env_variable'];
					$envValue = $row['variable_value'] ?? $row['default_value'] ?? '';
					$userServers[$serverId]['environment']->$envKey = $envValue;
				}
			}
		}

		// Build response data
		$attributes = [
			'id' => (int) $user['id'],
			'external_id' => $user['external_id'] ?? null,
			'uuid' => $user['uuid'],
			'username' => $user['username'],
			'email' => $user['email'],
			'first_name' => $user['first_name'] ?? null,
			'last_name' => $user['last_name'] ?? null,
			'language' => 'en',
			'root_admin' => (bool) (PermissionHelper::hasPermission($user['uuid'], Permissions::ADMIN_ROOT) ?? false),
			'2fa' => $user['two_fa_enabled'],
			'created_at' => DateTimePtero::format($user['first_seen']) ?? DateTimePtero::format($user['first_seen']),
			'updated_at' => DateTimePtero::format($user['last_seen']) ?? DateTimePtero::format($user['last_seen']),
		];

		if ($includeServers) {
			$serverData = [];
			foreach ($userServers as $server) {
				$serverData[] = [
					'object' => 'server',
					'attributes' => [
						'id' => $server['id'],
						'external_id' => $server['external_id'],
						'uuid' => $server['uuid'],
						'identifier' => $server['uuidShort'],
						'name' => $server['name'],
						'description' => $server['description'],
						'status' => $server['status'],
						'suspended' => $server['suspended'],
						'limits' => [
							'memory' => $server['memory'],
							'swap' => $server['swap'],
							'disk' => $server['disk'],
							'io' => $server['io'],
							'cpu' => $server['cpu'],
							'threads' => $server['threads'],
							'oom_disabled' => $server['oom_disabled'],
						],
						'feature_limits' => [
							'databases' => $server['database_limit'],
							'allocations' => $server['allocation_limit'],
							'backups' => $server['backup_limit'],
						],
						'user' => $server['owner_id'],
						'node' => $server['node_id'],
						'allocation' => $server['allocation_id'],
						'nest' => $server['realms_id'],
						'egg' => $server['spell_id'],
						'container' => [
							'startup_command' => $server['startup'],
							'image' => $server['image'],
							'installed' => $server['installed_at'] ? 1 : 0,
							'environment' => $server['environment'],
						],
						'updated_at' => DateTimePtero::format($server['updated_at']) ?? DateTimePtero::format($server['updated_at']),
						'created_at' => DateTimePtero::format($server['created_at']) ?? DateTimePtero::format($server['created_at']),
					],
				];
			}

			$attributes['relationships'] = [
				'servers' => [
					'object' => 'list',
					'data' => $serverData,
				],
			];
		}

		$responseData = [
			'object' => 'user',
			'attributes' => $attributes,
		];

		return ApiResponse::sendManualResponse($responseData, 200);
	}

	#[OA\Get(
		path: '/api/application/users/external/{externalId}',
		summary: 'Get user by external ID',
		description: 'Retrieve user details using external ID',
		tags: ['Plugin - Pterodactyl API - Users'],
		parameters: [
			new OA\Parameter(
				name: 'externalId',
				description: 'The external ID of the user',
				in: 'path',
				required: true,
				schema: new OA\Schema(type: 'string')
			),
			new OA\Parameter(
				name: 'include',
				description: 'Comma-separated list of relationships to include (servers)',
				in: 'query',
				required: false,
				schema: new OA\Schema(type: 'string', enum: ['servers'])
			)
		],
		responses: [
			new OA\Response(
				response: 200,
				description: 'User details retrieved successfully',
				content: new OA\JsonContent(
					type: 'object',
					properties: [
						new OA\Property(property: 'object', type: 'string', example: 'user'),
						new OA\Property(
							property: 'attributes',
							type: 'object',
							properties: [
								new OA\Property(property: 'id', type: 'integer'),
								new OA\Property(property: 'external_id', type: 'string'),
								new OA\Property(property: 'uuid', type: 'string'),
								new OA\Property(property: 'username', type: 'string'),
								new OA\Property(property: 'email', type: 'string'),
								new OA\Property(property: 'first_name', type: 'string'),
								new OA\Property(property: 'last_name', type: 'string'),
								new OA\Property(property: 'language', type: 'string'),
								new OA\Property(property: 'admin', type: 'boolean'),
								new OA\Property(property: 'root_admin', type: 'boolean'),
								new OA\Property(property: '2fa', type: 'boolean'),
								new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
								new OA\Property(property: 'updated_at', type: 'string', format: 'date-time')
							]
						)
					]
				)
			),
			new OA\Response(response: 404, description: 'User not found'),
			new OA\Response(response: 500, description: 'Internal server error')
		]
	)]
	public function showExternal(Request $request, string $externalId): Response
	{
		// Parse include parameter
		$include = $request->query->get('include', '');
		$includeServers = in_array('servers', explode(',', $include), true);

		$pdo = App::getInstance(true)->getDatabase()->getPdo();

		// Build the main query with conditional JOINs
		if ($includeServers) {
			// Query with JOINs to get user, servers, and variables in one go
			$sql = "SELECT 
						u.*,
						s.id as server_id,
						s.external_id as server_external_id,
						s.uuid as server_uuid,
						s.uuidShort as server_uuidShort,
						s.name as server_name,
						s.description as server_description,
						s.status as server_status,
						s.suspended as server_suspended,
						s.memory as server_memory,
						s.swap as server_swap,
						s.disk as server_disk,
						s.io as server_io,
						s.cpu as server_cpu,
						s.threads as server_threads,
						s.oom_disabled as server_oom_disabled,
						s.database_limit as server_database_limit,
						s.allocation_limit as server_allocation_limit,
						s.backup_limit as server_backup_limit,
						s.owner_id as server_owner_id,
						s.node_id as server_node_id,
						s.allocation_id as server_allocation_id,
						s.realms_id as server_realms_id,
						s.spell_id as server_spell_id,
						s.startup as server_startup,
						s.image as server_image,
						s.installed_at as server_installed_at,
						s.updated_at as server_updated_at,
						s.created_at as server_created_at,
						sv.variable_id,
						sv.variable_value,
						spv.env_variable,
						spv.default_value
					FROM " . self::USERS_TABLE . " u
					LEFT JOIN " . self::SERVERS_TABLE . " s ON u.id = s.owner_id
					LEFT JOIN " . self::SERVERS_TABLE_VARIABLES . " sv ON s.id = sv.server_id
					LEFT JOIN " . self::SPELLS_TABLE_VARIABLES . " spv ON sv.variable_id = spv.id
					WHERE u.external_id = :external_id
					ORDER BY s.id, sv.variable_id";
		} else {
			// Simple query for user only
			$sql = "SELECT * FROM " . self::USERS_TABLE . " WHERE external_id = :external_id";
		}

		$stmt = $pdo->prepare($sql);
		$stmt->bindValue(':external_id', $externalId);
		$stmt->execute();
		$results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		// Check if user exists
		if (empty($results)) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'NotFoundHttpException',
						'status' => "404",
						'detail' => 'The requested resource could not be found on the server.'
					]
				]
			], 404);
		}

		$user = $results[0];
		$userServers = [];

		// Process results and group by server
		foreach ($results as $row) {
			// Add server data if exists and servers are included
			if ($includeServers && $row['server_id']) {
				$serverId = (int) $row['server_id'];

				// Initialize server if not exists
				if (!isset($userServers[$serverId])) {
					$userServers[$serverId] = [
						'id' => $serverId,
						'external_id' => $row['server_external_id'] ?? null,
						'uuid' => $row['server_uuid'],
						'uuidShort' => $row['server_uuidShort'],
						'name' => $row['server_name'],
						'description' => $row['server_description'],
						'status' => $row['server_status'],
						'suspended' => (bool) $row['server_suspended'],
						'memory' => (int) $row['server_memory'],
						'swap' => (int) $row['server_swap'],
						'disk' => (int) $row['server_disk'],
						'io' => (int) $row['server_io'],
						'cpu' => (int) $row['server_cpu'],
						'threads' => $row['server_threads'],
						'oom_disabled' => (bool) $row['server_oom_disabled'],
						'database_limit' => (int) $row['server_database_limit'],
						'allocation_limit' => (int) $row['server_allocation_limit'],
						'backup_limit' => (int) $row['server_backup_limit'],
						'owner_id' => (int) $row['server_owner_id'],
						'node_id' => (int) $row['server_node_id'],
						'allocation_id' => (int) $row['server_allocation_id'],
						'realms_id' => (int) $row['server_realms_id'],
						'spell_id' => (int) $row['server_spell_id'],
						'startup' => $row['server_startup'],
						'image' => $row['server_image'],
						'installed_at' => DateTimePtero::format($row['server_installed_at']) ?? DateTimePtero::format($row['server_installed_at']),
						'updated_at' => DateTimePtero::format($row['server_updated_at']) ?? DateTimePtero::format($row['server_updated_at']),
						'created_at' => DateTimePtero::format($row['server_created_at']) ?? DateTimePtero::format($row['server_created_at']),
						'environment' => new \stdClass()
					];
				}

				// Add environment variable if exists
				if ($row['variable_id'] && $row['env_variable']) {
					$envKey = $row['env_variable'];
					$envValue = $row['variable_value'] ?? $row['default_value'] ?? '';
					$userServers[$serverId]['environment']->$envKey = $envValue;
				}
			}
		}

		// Build response data
		$attributes = [
			'id' => (int) $user['id'],
			'external_id' => $user['external_id'] ?? null,
			'uuid' => $user['uuid'],
			'username' => $user['username'],
			'email' => $user['email'],
			'first_name' => $user['first_name'] ?? null,
			'last_name' => $user['last_name'] ?? null,
			'language' => 'en',
			'root_admin' => (bool) (PermissionHelper::hasPermission($user['uuid'], Permissions::ADMIN_ROOT) ?? false),
			'2fa' => $user['two_fa_enabled'],
			'created_at' => DateTimePtero::format($user['first_seen']) ?? DateTimePtero::format($user['first_seen']),
			'updated_at' => DateTimePtero::format($user['last_seen']) ?? DateTimePtero::format($user['last_seen']),
		];

		if ($includeServers) {
			$serverData = [];
			foreach ($userServers as $server) {
				$serverData[] = [
					'object' => 'server',
					'attributes' => [
						'id' => $server['id'],
						'external_id' => $server['external_id'],
						'uuid' => $server['uuid'],
						'identifier' => $server['uuidShort'],
						'name' => $server['name'],
						'description' => $server['description'],
						'status' => $server['status'],
						'suspended' => $server['suspended'],
						'limits' => [
							'memory' => $server['memory'],
							'swap' => $server['swap'],
							'disk' => $server['disk'],
							'io' => $server['io'],
							'cpu' => $server['cpu'],
							'threads' => $server['threads'],
							'oom_disabled' => $server['oom_disabled'],
						],
						'feature_limits' => [
							'databases' => $server['database_limit'],
							'allocations' => $server['allocation_limit'],
							'backups' => $server['backup_limit'],
						],
						'user' => $server['owner_id'],
						'node' => $server['node_id'],
						'allocation' => $server['allocation_id'],
						'nest' => $server['realms_id'],
						'egg' => $server['spell_id'],
						'container' => [
							'startup_command' => $server['startup'],
							'image' => $server['image'],
							'installed' => $server['installed_at'] ? 1 : 0,
							'environment' => $server['environment'],
						],
						'updated_at' => DateTimePtero::format($server['updated_at']) ?? DateTimePtero::format($server['updated_at']),
						'created_at' => DateTimePtero::format($server['created_at']) ?? DateTimePtero::format($server['created_at']),
					],
				];
			}

			$attributes['relationships'] = [
				'servers' => [
					'object' => 'list',
					'data' => $serverData,
				],
			];
		}

		$responseData = [
			'object' => 'user',
			'attributes' => $attributes,
		];

		return ApiResponse::sendManualResponse($responseData, 200);
	}

	#[OA\Post(
		path: '/api/application/users',
		summary: 'Create a new user',
		description: 'Create a new user with the specified configuration',
		tags: ['Plugin - Pterodactyl API - Users'],
		requestBody: new OA\RequestBody(
			required: true,
			content: new OA\JsonContent(
				type: 'object',
				required: ['username', 'email', 'first_name', 'last_name'],
				properties: [
					new OA\Property(property: 'username', type: 'string', description: 'Username'),
					new OA\Property(property: 'email', type: 'string', format: 'email', description: 'User email'),
					new OA\Property(property: 'first_name', type: 'string', description: 'First name'),
					new OA\Property(property: 'last_name', type: 'string', description: 'Last name'),
					new OA\Property(property: 'password', type: 'string', description: 'User password'),
					new OA\Property(property: 'language', type: 'string', description: 'User language'),
					new OA\Property(property: 'admin', type: 'boolean', description: 'Whether user is admin'),
					new OA\Property(property: 'root_admin', type: 'boolean', description: 'Whether user is root admin'),
					new OA\Property(property: 'external_id', type: 'string', description: 'External ID for integration')
				]
			)
		),
		responses: [
			new OA\Response(
				response: 201,
				description: 'User created successfully',
				content: new OA\JsonContent(
					type: 'object',
					properties: [
						new OA\Property(property: 'object', type: 'string', example: 'user'),
						new OA\Property(
							property: 'attributes',
							type: 'object',
							properties: [
								new OA\Property(property: 'id', type: 'integer'),
								new OA\Property(property: 'external_id', type: 'string'),
								new OA\Property(property: 'uuid', type: 'string'),
								new OA\Property(property: 'username', type: 'string'),
								new OA\Property(property: 'email', type: 'string'),
								new OA\Property(property: 'first_name', type: 'string'),
								new OA\Property(property: 'last_name', type: 'string'),
								new OA\Property(property: 'language', type: 'string'),
								new OA\Property(property: 'admin', type: 'boolean'),
								new OA\Property(property: 'root_admin', type: 'boolean'),
								new OA\Property(property: '2fa', type: 'boolean'),
								new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
								new OA\Property(property: 'updated_at', type: 'string', format: 'date-time')
							]
						)
					]
				)
			),
			new OA\Response(response: 400, description: 'Invalid request data'),
			new OA\Response(response: 422, description: 'Validation failed'),
			new OA\Response(response: 500, description: 'Internal server error')
		]
	)]
	public function store(Request $request): Response
	{
		// Get request data
		$data = json_decode($request->getContent(), true);

		// Required fields for user creation
		$requiredFields = ['username', 'first_name', 'last_name', 'email'];
		$missingFields = [];
		foreach ($requiredFields as $field) {
			if (!isset($data[$field]) || trim($data[$field]) === '') {
				$missingFields[] = $field;
			}
		}
		if (!empty($missingFields)) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'ValidationException',
						'status' => '422',
						'detail' => 'Missing required fields: ' . implode(', ', $missingFields)
					]
				]
			], 422);
		}

		// Validate data types and format
		foreach ($requiredFields as $field) {
			if (!is_string($data[$field])) {
				return ApiResponse::sendManualResponse([
					'errors' => [
						[
							'code' => 'ValidationException',
							'status' => '422',
							'detail' => ucfirst(str_replace('_', ' ', $field)) . ' must be a string'
						]
					]
				], 422);
			}
			$data[$field] = trim($data[$field]);
		}

		// Validate data length
		$lengthRules = [
			'username' => [3, 32],
			'first_name' => [1, 64],
			'last_name' => [1, 64],
			'email' => [3, 255],
		];
		foreach ($lengthRules as $field => [$min, $max]) {
			$len = strlen($data[$field]);
			if ($len < $min) {
				return ApiResponse::sendManualResponse([
					'errors' => [
						[
							'code' => 'ValidationException',
							'status' => '422',
							'detail' => ucfirst(str_replace('_', ' ', $field)) . " must be at least $min characters long"
						]
					]
				], 422);
			}
			if ($len > $max) {
				return ApiResponse::sendManualResponse([
					'errors' => [
						[
							'code' => 'ValidationException',
							'status' => '422',
							'detail' => ucfirst(str_replace('_', ' ', $field)) . " must be less than $max characters long"
						]
					]
				], 422);
			}
		}

		// Validate email format
		if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'ValidationException',
						'status' => '422',
						'detail' => 'Invalid email address'
					]
				]
			], 422);
		}

		// Check for existing email/username
		if (User::getUserByEmail($data['email'])) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'ValidationException',
						'status' => '422',
						'detail' => 'Email already exists'
					]
				]
			], 422);
		}
		if (User::getUserByUsername($data['username'])) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'ValidationException',
						'status' => '422',
						'detail' => 'Username already exists'
					]
				]
			], 422);
		}

		// Check if external_id already exists (if provided)
		if (!empty($data['external_id'])) {
			$existingUser = User::getUserByExternalId($data['external_id']);
			if ($existingUser) {
				return ApiResponse::sendManualResponse([
					'errors' => [
						[
							'code' => 'ValidationException',
							'status' => '422',
							'detail' => 'External ID already exists'
						]
					]
				], 422);
			}
		}

		// Hash password if provided
		if (!empty($data['password'])) {
			$data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
		}

		// Generate UUID
		$data['uuid'] = UUIDUtils::generateV4();
		$data['remember_token'] = User::generateAccountToken();

		// Set default avatar if not provided
		if (empty($data['avatar'])) {
			$data['avatar'] = 'https://cdn.mythical.systems/featherpanel/logo.png';
		}

		// Set default role if not provided
		if (!isset($data['root_admin'])) {
			$data['role_id'] = 1; // Default to user role
		} else {
			if ($data['root_admin'] === true) {
				$data['role_id'] = 4; // Admin role
			} else {
				$data['role_id'] = 1; // User role
			}
		}

		// Set default values for Pterodactyl API compatibility
		unset($data['root_admin']);
		unset($data['two_fa_enabled']);
		unset($data['language']);

		// Create user using the User model
		$userId = User::createUser($data);
		if (!$userId) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'InternalServerError',
						'status' => '500',
						'detail' => 'Failed to create user'
					]
				]
			], 500);
		}

		// Send welcome email (following Admin API pattern)
		$config = App::getInstance(true)->getConfig();
		\App\Mail\templates\Welcome::send([
			'email' => $data['email'],
			'subject' => 'Welcome to ' . $config->getSetting(\App\Config\ConfigInterface::APP_NAME, 'FeatherPanel'),
			'app_name' => $config->getSetting(\App\Config\ConfigInterface::APP_NAME, 'FeatherPanel'),
			'app_url' => $config->getSetting(\App\Config\ConfigInterface::APP_URL, 'featherpanel.mythical.systems'),
			'first_name' => $data['first_name'],
			'last_name' => $data['last_name'],
			'username' => $data['username'],
			'app_support_url' => $config->getSetting(\App\Config\ConfigInterface::APP_SUPPORT_URL, 'https://discord.mythical.systems'),
			'uuid' => $data['uuid'],
			'enabled' => $config->getSetting(\App\Config\ConfigInterface::SMTP_ENABLED, 'false'),
		]);

		// Create activity logs (following Admin API pattern)
		\App\Chat\Activity::createActivity([
			'user_uuid' => $data['uuid'],
			'name' => 'register',
			'context' => 'User registered via Pterodactyl API',
			'ip_address' => '0.0.0.0',
		]);

		// Get the requesting user safely
		$requestingUser = $request->get('user');
		if ($requestingUser && isset($requestingUser['uuid'])) {
			\App\Chat\Activity::createActivity([
				'user_uuid' => $requestingUser['uuid'],
				'name' => 'create_user',
				'context' => 'Created a new user ' . $data['username'] . ' via Pterodactyl API',
				'ip_address' => \App\CloudFlare\CloudFlareRealIP::getRealIP(),
			]);
		}

		// Get the created user for response
		$createdUser = User::getUserById($userId);

		// Return the created user in Pterodactyl API format
		$responseData = [
			'object' => 'user',
			'attributes' => [
				'id' => (int) $userId,
				'external_id' => $createdUser['external_id'] ?? null,
				'uuid' => $createdUser['uuid'],
				'username' => $createdUser['username'],
				'email' => $createdUser['email'],
				'first_name' => $createdUser['first_name'],
				'last_name' => $createdUser['last_name'],
				'language' => 'en',
				'root_admin' => (bool) (PermissionHelper::hasPermission($createdUser['uuid'], Permissions::ADMIN_ROOT) ?? false),
				'2fa' => (bool) $createdUser['two_fa_enabled'],
				'created_at' => DateTimePtero::format($createdUser['first_seen']),
				'updated_at' => DateTimePtero::format($createdUser['last_seen']),
			],
			'meta' => [
				'resource' => App::getInstance(true)->getConfig()->getSetting(ConfigInterface::APP_URL, 'http://localhost') . '/api/application/users/' . $createdUser['id'],
			]
		];

		return ApiResponse::sendManualResponse($responseData, 201);
	}

	#[OA\Patch(
		path: '/api/application/users/{userId}',
		summary: 'Update user',
		description: 'Update an existing user',
		tags: ['Plugin - Pterodactyl API - Users'],
		parameters: [
			new OA\Parameter(
				name: 'userId',
				description: 'The ID of the user',
				in: 'path',
				required: true,
				schema: new OA\Schema(type: 'integer')
			)
		],
		requestBody: new OA\RequestBody(
			required: true,
			content: new OA\JsonContent(
				type: 'object',
				properties: [
					new OA\Property(property: 'username', type: 'string', description: 'Username'),
					new OA\Property(property: 'email', type: 'string', format: 'email', description: 'User email'),
					new OA\Property(property: 'first_name', type: 'string', description: 'First name'),
					new OA\Property(property: 'last_name', type: 'string', description: 'Last name'),
					new OA\Property(property: 'password', type: 'string', description: 'User password'),
					new OA\Property(property: 'language', type: 'string', description: 'User language'),
					new OA\Property(property: 'admin', type: 'boolean', description: 'Whether user is admin'),
					new OA\Property(property: 'root_admin', type: 'boolean', description: 'Whether user is root admin'),
					new OA\Property(property: 'external_id', type: 'string', description: 'External ID for integration')
				]
			)
		),
		responses: [
			new OA\Response(
				response: 200,
				description: 'User updated successfully',
				content: new OA\JsonContent(
					type: 'object',
					properties: [
						new OA\Property(property: 'object', type: 'string', example: 'user'),
						new OA\Property(
							property: 'attributes',
							type: 'object',
							properties: [
								new OA\Property(property: 'id', type: 'integer'),
								new OA\Property(property: 'external_id', type: 'string'),
								new OA\Property(property: 'uuid', type: 'string'),
								new OA\Property(property: 'username', type: 'string'),
								new OA\Property(property: 'email', type: 'string'),
								new OA\Property(property: 'first_name', type: 'string'),
								new OA\Property(property: 'last_name', type: 'string'),
								new OA\Property(property: 'language', type: 'string'),
								new OA\Property(property: 'admin', type: 'boolean'),
								new OA\Property(property: 'root_admin', type: 'boolean'),
								new OA\Property(property: '2fa', type: 'boolean'),
								new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
								new OA\Property(property: 'updated_at', type: 'string', format: 'date-time')
							]
						)
					]
				)
			),
			new OA\Response(response: 400, description: 'Invalid request data'),
			new OA\Response(response: 404, description: 'User not found'),
			new OA\Response(response: 422, description: 'Validation failed'),
			new OA\Response(response: 500, description: 'Internal server error')
		]
	)]
	public function update(Request $request, int $userId): Response
	{
		// Get request data
		$data = json_decode($request->getContent(), true);

		// Check if user exists
		$user = User::getUserById($userId);
		if (!$user) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'NotFoundHttpException',
						'status' => '404',
						'detail' => 'The requested resource could not be found on the server.'
					]
				]
			], 404);
		}

		// Validate provided fields
		$fieldsToValidate = [];
		foreach (['username', 'first_name', 'last_name', 'email'] as $field) {
			if (isset($data[$field])) {
				$fieldsToValidate[$field] = $data[$field];
			}
		}

		// Validate data types and format
		foreach ($fieldsToValidate as $field => $value) {
			if (!is_string($value)) {
				return ApiResponse::sendManualResponse([
					'errors' => [
						[
							'code' => 'ValidationException',
							'status' => '422',
							'detail' => ucfirst(str_replace('_', ' ', $field)) . ' must be a string'
						]
					]
				], 422);
			}
			$data[$field] = trim($value);
		}

		// Validate data length
		$lengthRules = [
			'username' => [3, 32],
			'first_name' => [1, 64],
			'last_name' => [1, 64],
			'email' => [3, 255],
		];
		foreach ($lengthRules as $field => [$min, $max]) {
			if (isset($data[$field])) {
				$len = strlen($data[$field]);
				if ($len < $min) {
					return ApiResponse::sendManualResponse([
						'errors' => [
							[
								'code' => 'ValidationException',
								'status' => '422',
								'detail' => ucfirst(str_replace('_', ' ', $field)) . " must be at least $min characters long"
							]
						]
					], 422);
				}
				if ($len > $max) {
					return ApiResponse::sendManualResponse([
						'errors' => [
							[
								'code' => 'ValidationException',
								'status' => '422',
								'detail' => ucfirst(str_replace('_', ' ', $field)) . " must be less than $max characters long"
							]
						]
					], 422);
				}
			}
		}

		// Validate email format if provided
		if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'ValidationException',
						'status' => '422',
						'detail' => 'Invalid email address'
					]
				]
			], 422);
		}

		// Check for existing email/username (excluding current user)
		if (isset($data['email'])) {
			$existingUser = User::getUserByEmail($data['email']);
			if ($existingUser && $existingUser['id'] != $userId) {
				return ApiResponse::sendManualResponse([
					'errors' => [
						[
							'code' => 'ValidationException',
							'status' => '422',
							'detail' => 'Email already exists'
						]
					]
				], 422);
			}
		}
		if (isset($data['username'])) {
			$existingUser = User::getUserByUsername($data['username']);
			if ($existingUser && $existingUser['id'] != $userId) {
				return ApiResponse::sendManualResponse([
					'errors' => [
						[
							'code' => 'ValidationException',
							'status' => '422',
							'detail' => 'Username already exists'
						]
					]
				], 422);
			}
		}

		// Check if external_id already exists (if provided, excluding current user)
		if (isset($data['external_id']) && !empty($data['external_id'])) {
			$existingUser = User::getUserByExternalId($data['external_id']);
			if ($existingUser && $existingUser['id'] != $userId) {
				return ApiResponse::sendManualResponse([
					'errors' => [
						[
							'code' => 'ValidationException',
							'status' => '422',
							'detail' => 'External ID already exists'
						]
					]
				], 422);
			}
		}

		// Hash password if provided
		if (isset($data['password']) && !empty($data['password'])) {
			$data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
		}

		// Handle root_admin role assignment
		if (isset($data['root_admin'])) {
			if ($data['root_admin'] === true) {
				$data['role_id'] = 4; // Admin role
			} else {
				$data['role_id'] = 1; // User role
			}
			unset($data['root_admin']);
		}

		// Remove fields that shouldn't be updated directly
		unset($data['two_fa_enabled']);
		unset($data['language']);

		// Update user using the User model
		$success = User::updateUser($userId, $data);
		if (!$success) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'InternalServerError',
						'status' => '500',
						'detail' => 'Failed to update user'
					]
				]
			], 500);
		}

		// Get the updated user for response
		$updatedUser = User::getUserById($userId);

		// Return the updated user in Pterodactyl API format
		$responseData = [
			'object' => 'user',
			'attributes' => [
				'id' => (int) $userId,
				'external_id' => $updatedUser['external_id'] ?? null,
				'uuid' => $updatedUser['uuid'],
				'username' => $updatedUser['username'],
				'email' => $updatedUser['email'],
				'first_name' => $updatedUser['first_name'],
				'last_name' => $updatedUser['last_name'],
				'language' => 'en',
				'root_admin' => (bool) (PermissionHelper::hasPermission($updatedUser['uuid'], Permissions::ADMIN_ROOT) ?? false),
				'2fa' => (bool) $updatedUser['two_fa_enabled'],
				'created_at' => DateTimePtero::format($updatedUser['first_seen']),
				'updated_at' => DateTimePtero::format($updatedUser['last_seen']),
			],
		];

		return ApiResponse::sendManualResponse($responseData, 200);
	}

	#[OA\Delete(
		path: '/api/application/users/{userId}',
		summary: 'Delete user',
		description: 'Delete a user from the panel',
		tags: ['Plugin - Pterodactyl API - Users'],
		parameters: [
			new OA\Parameter(
				name: 'userId',
				description: 'The ID of the user',
				in: 'path',
				required: true,
				schema: new OA\Schema(type: 'integer')
			)
		],
		responses: [
			new OA\Response(response: 204, description: 'User deleted successfully'),
			new OA\Response(response: 404, description: 'User not found'),
			new OA\Response(response: 400, description: 'Cannot delete user with servers'),
			new OA\Response(response: 500, description: 'Internal server error')
		]
	)]
	public function destroy(Request $request, int $userId): Response
	{
		// Check if user exists
		$user = User::getUserById($userId);
		if (!$user) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'NotFoundHttpException',
						'status' => '404',
						'detail' => 'The requested resource could not be found on the server.'
					]
				]
			], 404);
		}

		// Check if user has any servers using the same pattern as Admin API
		$servers = \App\Chat\Server::searchServers(
			page: 1,
			limit: 1,
			search: '',
			fields: ['id'],
			sortBy: 'id',
			sortOrder: 'ASC',
			ownerId: (int) $user['id']
		);

		if (!empty($servers)) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'DisplayException',
						'status' => '400',
						'detail' => 'Cannot delete a user with active servers attached to their account. Please delete their servers before continuing.'
					]
				]
			], 400);
		}


		// Create activity log (following Admin API pattern)
		\App\Chat\Activity::createActivity([
			'user_uuid' => $user['uuid'],
			'name' => 'delete_user',
			'context' => 'User deleted via Pterodactyl API',
			'ip_address' => \App\CloudFlare\CloudFlareRealIP::getRealIP(),
		]);

		// Comprehensive cleanup (following Admin API pattern)
		\App\Chat\Activity::deleteUserData($user['uuid']);
		\App\Chat\MailList::deleteAllMailListsByUserId($user['uuid']);
		\App\Chat\ApiClient::deleteAllApiClientsByUserId($user['uuid']);
		\App\Chat\Subuser::deleteAllSubusersByUserId((int) $user['id']);
		\App\Chat\MailQueue::deleteAllMailQueueByUserId($user['uuid']);

		// Delete user using hardDeleteUser (following Admin API pattern)
		$deleted = User::hardDeleteUser($user['id']);
		if (!$deleted) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'InternalServerError',
						'status' => '500',
						'detail' => 'Failed to delete user'
					]
				]
			], 500);
		}

		// Return 204 No Content on successful deletion
		return new Response('', 204);
	}
}


