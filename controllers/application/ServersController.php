<?php

/*
 * This file is part of FeatherPanel.
 * Please view the LICENSE file that was distributed with this source code.
 *
 * # MythicalSystems License v2.0
 *
 * ## Copyright (c) 2021–2025 MythicalSystems and Cassian Gherman
 *
 * Breaking any of the following rules will result in a permanent ban from the MythicalSystems community and all of its services.
 */

namespace App\Addons\pterodactylpanelapi\controllers\application;

use App\App;
use App\Chat\Server;
use App\Chat\User;
use App\Chat\Node;
use App\Chat\Realm;
use App\Chat\Spell;
use App\Chat\Allocation;
use App\Chat\Subuser;
use App\Chat\Backup;
use App\Chat\ServerDatabase;
use App\Chat\Location;
use App\Chat\ServerVariable;
use App\Chat\SpellVariable;
use App\Chat\Activity;
use App\Helpers\ApiResponse;
use App\Config\ConfigInterface;
use App\CloudFlare\CloudFlareRealIP;
use App\Mail\templates\ServerCreated;
use App\Mail\templates\ServerBanned;
use App\Mail\templates\ServerUnbanned;
use App\Mail\templates\ServerDeleted;
use App\Addons\pterodactylpanelapi\utils\DateTimePtero;
use App\Addons\pterodactylpanelapi\middleware\PterodactylKeyAuth;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[OA\Tag(name: 'Plugin - Pterodactyl API - Servers', description: 'Server management endpoints for Pterodactyl Panel API plugin')]
class ServersController
{
	// Table names
	const SERVERS_TABLE = 'featherpanel_servers';
	const SERVER_VARIABLES_TABLE = 'featherpanel_server_variables';
	const SPELL_VARIABLES_TABLE = 'featherpanel_spell_variables';

	#[OA\Get(
		path: '/api/application/servers',
		summary: 'List all servers',
		description: 'Retrieve a paginated list of all servers with optional filtering',
		tags: ['Plugin - Pterodactyl API - Servers'],
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
				name: 'filter[uuid]',
				description: 'Filter by server UUID',
				in: 'query',
				required: false,
				schema: new OA\Schema(type: 'string')
			),
			new OA\Parameter(
				name: 'filter[name]',
				description: 'Filter by server name',
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
				name: 'include',
				description: 'Comma-separated list of relationships to include (user, subusers, allocations, nest, egg, variables)',
				in: 'query',
				required: false,
				schema: new OA\Schema(type: 'string', enum: ['user', 'subusers', 'allocations', 'nest', 'egg', 'variables'])
			)
		],
		responses: [
			new OA\Response(
				response: 200,
				description: 'List of servers retrieved successfully',
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
									new OA\Property(property: 'object', type: 'string', example: 'server'),
									new OA\Property(
										property: 'attributes',
										type: 'object',
										properties: [
											new OA\Property(property: 'id', type: 'integer'),
											new OA\Property(property: 'external_id', type: 'string'),
											new OA\Property(property: 'uuid', type: 'string'),
											new OA\Property(property: 'identifier', type: 'string'),
											new OA\Property(property: 'name', type: 'string'),
											new OA\Property(property: 'description', type: 'string'),
											new OA\Property(property: 'suspended', type: 'boolean'),
											new OA\Property(property: 'limits', type: 'object'),
											new OA\Property(property: 'feature_limits', type: 'object'),
											new OA\Property(property: 'user', type: 'integer'),
											new OA\Property(property: 'node', type: 'integer'),
											new OA\Property(property: 'allocation', type: 'integer'),
											new OA\Property(property: 'nest', type: 'integer'),
											new OA\Property(property: 'egg', type: 'integer'),
											new OA\Property(property: 'container', type: 'object'),
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
		// Get pagination parameters
		$page = (int) $request->query->get('page', 1);
		$perPage = (int) $request->query->get('per_page', 50);

		// Get filter parameters
		$nameFilter = $request->query->get('filter[name]', '');
		$uuidFilter = $request->query->get('filter[uuid]', '');
		$externalIdFilter = $request->query->get('filter[external_id]', '');
		$imageFilter = $request->query->get('filter[image]', '');

		// Get sort parameter
		$sort = $request->query->get('sort', 'id');

		// Get include parameter
		$include = $request->query->get('include', '');
		$includeServers = strpos($include, 'user') !== false || strpos($include, 'node') !== false || strpos($include, 'allocations') !== false || strpos($include, 'subusers') !== false || strpos($include, 'nest') !== false || strpos($include, 'egg') !== false || strpos($include, 'variables') !== false || strpos($include, 'location') !== false || strpos($include, 'databases') !== false || strpos($include, 'backups') !== false;

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

		$pdo = App::getInstance(true)->getDatabase()->getPdo();

		// Build the base query
		$whereConditions = [];
		$params = [];

		// Add filters
		if (!empty($nameFilter)) {
			$whereConditions[] = "s.name LIKE :name_filter";
			$params[':name_filter'] = '%' . $nameFilter . '%';
		}
		if (!empty($uuidFilter)) {
			$whereConditions[] = "s.uuid = :uuid_filter";
			$params[':uuid_filter'] = $uuidFilter;
		}
		if (!empty($externalIdFilter)) {
			$whereConditions[] = "s.external_id = :external_id_filter";
			$params[':external_id_filter'] = $externalIdFilter;
		}
		if (!empty($imageFilter)) {
			$whereConditions[] = "s.image LIKE :image_filter";
			$params[':image_filter'] = '%' . $imageFilter . '%';
		}

		$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

		// Validate sort parameter
		$allowedSorts = ['id', 'uuid', 'name', 'created_at', 'updated_at'];
		if (!in_array($sort, $allowedSorts)) {
			$sort = 'id';
		}

		// Build the main query with JOINs for environment variables (always included)
		$sql = "SELECT 
			s.id,
			s.external_id,
			s.uuid,
			s.uuidShort as identifier,
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
			s.owner_id as user,
			s.node_id as node,
			s.allocation_id as allocation,
			s.realms_id as nest,
			s.spell_id as egg,
			s.startup as startup_command,
			s.image,
			s.installed_at as installed,
			s.created_at,
			s.updated_at,
			sv.variable_value,
			spv.env_variable,
			spv.default_value
		FROM " . self::SERVERS_TABLE . " s
		LEFT JOIN " . self::SERVER_VARIABLES_TABLE . " sv ON s.id = sv.server_id
		LEFT JOIN " . self::SPELL_VARIABLES_TABLE . " spv ON sv.variable_id = spv.id
		$whereClause
		ORDER BY s.$sort ASC
		LIMIT :limit OFFSET :offset";

		$stmt = $pdo->prepare($sql);
		$stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
		$stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);

		// Bind filter parameters
		foreach ($params as $key => $value) {
			$stmt->bindValue($key, $value);
		}

		$stmt->execute();
		$results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		// Get total count for pagination
		$countSql = "SELECT COUNT(*) FROM " . self::SERVERS_TABLE . " s $whereClause";
		$countStmt = $pdo->prepare($countSql);
		foreach ($params as $key => $value) {
			$countStmt->bindValue($key, $value);
		}
		$countStmt->execute();
		$total = (int) $countStmt->fetchColumn();

		$totalPages = ceil($total / $perPage);

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
			if (isset($server['egg'])) {
				$defaultVarsSql = "SELECT env_variable, default_value 
					FROM " . self::SPELL_VARIABLES_TABLE . " 
					WHERE spell_id = :spell_id 
					AND id IN (
						SELECT MAX(id) 
						FROM " . self::SPELL_VARIABLES_TABLE . " 
						WHERE spell_id = :spell_id 
						GROUP BY env_variable
					)";
				$defaultStmt = $pdo->prepare($defaultVarsSql);
				$defaultStmt->bindValue(':spell_id', $server['egg'], \PDO::PARAM_INT);
				$defaultStmt->execute();
				$defaultVars = $defaultStmt->fetchAll(\PDO::FETCH_ASSOC);

				foreach ($defaultVars as $var) {
					if ($var['env_variable'] && $var['default_value'] !== null) {
						$server['default_variables'][$var['env_variable']] = $var['default_value'];
					}
				}
			}
		}

		// Process servers data
		$processedServers = [];
		foreach ($servers as $server) {
			$processedServer = [
				'object' => 'server',
				'attributes' => [
					'id' => (int) $server['id'],
					'external_id' => $server['external_id'],
					'uuid' => $server['uuid'],
					'identifier' => $server['identifier'],
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
					'user' => (int) $server['user'],
					'node' => (int) $server['node'],
					'allocation' => (int) $server['allocation'],
					'nest' => (int) $server['nest'],
					'egg' => (int) $server['egg'],
					'container' => [
						'startup_command' => $server['startup_command'],
						'image' => $server['image'],
						'installed' => $server['installed'] ? 1 : 0,
						'environment' => $this->buildEnvironmentVariables($server),
					],
					'created_at' => \App\Addons\pterodactylpanelapi\utils\DateTimePtero::format($server['created_at']),
					'updated_at' => \App\Addons\pterodactylpanelapi\utils\DateTimePtero::format($server['updated_at']),
				],
			];

			// Add relationships if include parameter is specified
			if ($includeServers) {
				$relationships = [];

				// Include user if requested
				if (strpos($include, 'user') !== false) {
					$user = User::getUserById($server['user']);
					if ($user) {
						$relationships['user'] = [
							'object' => 'user',
							'attributes' => [
								'id' => (int) $user['id'],
								'external_id' => (string) $user['external_id'],
								'uuid' => $user['uuid'],
								'username' => $user['username'],
								'email' => $user['email'],
								'first_name' => $user['first_name'],
								'last_name' => $user['last_name'],
								'language' => 'en',
								'root_admin' => (bool) ($user['role_id'] == 4),
								'2fa' => ($user['two_fa_enabled'] == "true") ? true : false,
								'created_at' => DateTimePtero::format($user['first_seen']),
								'updated_at' => DateTimePtero::format($user['last_seen']),
							],
						];
					}
				}

				// Include node if requested
				if (strpos($include, 'node') !== false) {
					$node = Node::getNodeById($server['node']);
					if ($node) {
						$relationships['node'] = [
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
				}

				// Include allocations if requested
				if (strpos($include, 'allocations') !== false) {
					$allocations = Allocation::getAll(null, null, (int) $server['id'], 1000, 0);
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
					if (!empty($allocationData)) {
						$relationships['allocations'] = [
							'object' => 'list',
							'data' => $allocationData,
						];
					}
				}

				// Include subusers if requested (always include like Pterodactyl)
				if (strpos($include, 'subusers') !== false || true) {
					$subusers = Subuser::getSubusersByServerId((int) $server['id']);
					$subuserData = [];
					foreach ($subusers as $subuser) {
						$subuserData[] = [
							'object' => 'subuser',
							'attributes' => [
								'id' => (int) $subuser['id'],
								'user_id' => (int) $subuser['user_id'],
								'permissions' => json_decode($subuser['permissions'], true),
								'created_at' => DateTimePtero::format($subuser['created_at']),
								'updated_at' => DateTimePtero::format($subuser['updated_at']),
							],
						];
					}
					// Always include subusers (even if empty, like Pterodactyl)
					$relationships['subusers'] = [
						'object' => 'list',
						'data' => $subuserData,
					];
				}

				// Include nest (realm) if requested
				if (strpos($include, 'nest') !== false) {
					$realm = Realm::getById($server['nest']);
					if ($realm) {
						$relationships['nest'] = [
							'object' => 'nest',
							'attributes' => [
								'id' => (int) $realm['id'],
								'uuid' => $realm['uuid'] ?? null,
								'author' => $realm['author'],
								'name' => $realm['name'],
								'description' => $realm['description'],
								'created_at' => DateTimePtero::format($realm['created_at']),
								'updated_at' => DateTimePtero::format($realm['updated_at']),
							],
						];
					}
				}

				// Include egg (spell) if requested
				if (strpos($include, 'egg') !== false) {
					$spell = Spell::getSpellById($server['egg']);
					if ($spell) {
						$relationships['egg'] = [
							'object' => 'egg',
							'attributes' => [
								'id' => (int) $spell['id'],
								'uuid' => $spell['uuid'] ?? null,
								'name' => $spell['name'],
								'nest' => (int) $spell['realm_id'],
								'author' => $spell['author'],
								'description' => $spell['description'],
								'docker_image' => $spell['docker_images'] ? json_decode($spell['docker_images'], true)[array_key_first(json_decode($spell['docker_images'], true))] ?? '' : '',
								'docker_images' => $spell['docker_images'] ? json_decode($spell['docker_images'], true) : [],
								'config' => [
									'files' => $spell['config_files'] ? json_decode($spell['config_files'], true) : [],
									'startup' => $spell['config_startup'] ? json_decode($spell['config_startup'], true) : [],
									'stop' => $spell['config_stop'] ?? '',
									'logs' => $spell['config_logs'] ? json_decode($spell['config_logs'], true) : [],
									'file_denylist' => $spell['file_denylist'] ? json_decode($spell['file_denylist'], true) : [],
									'extends' => $spell['config_from'] ? (int) $spell['config_from'] : null,
								],
								'startup' => $spell['startup'] ?? '',
								'script' => [
									'privileged' => (bool) $spell['script_is_privileged'],
									'install' => $spell['script_install'] ?? '',
									'entry' => $spell['script_entry'] ?? 'ash',
									'container' => $spell['script_container'] ?? 'alpine:3.4',
									'extends' => $spell['copy_script_from'] ? (int) $spell['copy_script_from'] : null,
								],
								'created_at' => DateTimePtero::format($spell['created_at']),
								'updated_at' => DateTimePtero::format($spell['updated_at']),
							],
						];
					}
				}

				// Include variables as separate relationship if requested
				if (strpos($include, 'variables') !== false) {
					$variablesSql = "SELECT 
						spv.id,
						spv.spell_id as egg_id,
						spv.name,
						spv.description,
						spv.env_variable,
						spv.default_value,
						spv.user_viewable,
						spv.user_editable,
						spv.rules,
						spv.created_at,
						spv.updated_at,
						COALESCE(sv.variable_value, spv.default_value) as server_value
					FROM " . self::SPELL_VARIABLES_TABLE . " spv
					LEFT JOIN " . self::SERVER_VARIABLES_TABLE . " sv ON spv.id = sv.variable_id AND sv.server_id = :server_id
					WHERE spv.spell_id = :spell_id
					AND spv.id IN (
						SELECT MAX(id) 
						FROM " . self::SPELL_VARIABLES_TABLE . " 
						WHERE spell_id = :spell_id 
						GROUP BY env_variable
					)
					ORDER BY spv.id";

					$variablesStmt = $pdo->prepare($variablesSql);
					$variablesStmt->bindValue(':server_id', $server['id'], \PDO::PARAM_INT);
					$variablesStmt->bindValue(':spell_id', $server['egg'], \PDO::PARAM_INT);
					$variablesStmt->execute();
					$variables = $variablesStmt->fetchAll(\PDO::FETCH_ASSOC);

					$variableData = [];
					foreach ($variables as $variable) {
						$variableData[] = [
							'object' => 'server_variable',
							'attributes' => [
								'id' => (int) $variable['id'],
								'egg_id' => (int) $variable['egg_id'],
								'name' => $variable['name'],
								'description' => $variable['description'],
								'env_variable' => $variable['env_variable'],
								'default_value' => $variable['default_value'],
								'user_viewable' => (bool) $variable['user_viewable'],
								'user_editable' => (bool) $variable['user_editable'],
								'rules' => $variable['rules'],
								'created_at' => DateTimePtero::format($variable['created_at']),
								'updated_at' => DateTimePtero::format($variable['updated_at']),
								'server_value' => $variable['server_value'],
							],
						];
					}
					if (!empty($variableData)) {
						$relationships['variables'] = [
							'object' => 'list',
							'data' => $variableData,
						];
					}
				}

				// Include location if requested
				if (strpos($include, 'location') !== false) {
					// Get location from node
					$node = Node::getNodeById($server['node']);
					if ($node && isset($node['location_id'])) {
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
				}

				// Include databases if requested (always include like Pterodactyl)
				if (strpos($include, 'databases') !== false || true) {
					$databases = ServerDatabase::getDatabasesByServerId((int) $server['id']);
					$databaseData = [];
					foreach ($databases as $database) {
						$databaseData[] = [
							'object' => 'databases',
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
					}
					// Always include databases (even if empty, like Pterodactyl)
					$relationships['databases'] = [
						'object' => 'list',
						'data' => $databaseData,
					];
				}

				// Include backups if requested
				if (strpos($include, 'backups') !== false) {
					$backups = Backup::getBackupsByServerId((int) $server['id']);
					$backupData = [];
					foreach ($backups as $backup) {
						$backupData[] = [
							'object' => 'backup',
							'attributes' => [
								'id' => (int) $backup['id'],
								'uuid' => $backup['uuid'],
								'server_id' => (int) $backup['server_id'],
								'name' => $backup['name'],
								'ignored_files' => $backup['ignored_files'],
								'disk' => (int) $backup['disk'],
								'created_at' => DateTimePtero::format($backup['created_at']),
								'updated_at' => DateTimePtero::format($backup['updated_at']),
							],
						];
					}
					if (!empty($backupData)) {
						$relationships['backups'] = [
							'object' => 'list',
							'data' => $backupData,
						];
					}
				}

				if (!empty($relationships)) {
					$processedServer['relationships'] = $relationships;
				}
			}

			$processedServers[] = $processedServer;
		}

		// Build pagination links like Pterodactyl
		$links = [];
		$baseUrl = App::getInstance(true)->getConfig()->getSetting(ConfigInterface::APP_URL, 'http://localhost');
		$currentParams = $request->query->all() ?? [];

		if ($page < $totalPages) {
			$nextParams = array_merge($currentParams, ['page' => $page + 1]);
			$links['next'] = $baseUrl . '/api/application/servers?' . http_build_query($nextParams);
		}
		if ($page > 1) {
			$prevParams = array_merge($currentParams, ['page' => $page - 1]);
			$links['previous'] = $baseUrl . '/api/application/servers?' . http_build_query($prevParams);
		}

		// Return the response in Pterodactyl API format
		$responseData = [
			'object' => 'list',
			'data' => $processedServers,
			'meta' => [
				'pagination' => [
					'total' => $total,
					'count' => count($processedServers),
					'per_page' => $perPage,
					'current_page' => $page,
					'total_pages' => $totalPages,
					'links' => $links,
				],
			],
		];

		return ApiResponse::sendManualResponse($responseData, 200);
	}

	/**
	 * Get Server Details - Retrieve detailed information about a specific server
	 */
	#[OA\Get(
		path: '/api/application/servers/{serverId}',
		summary: 'Get server details',
		description: 'Retrieve detailed information about a specific server',
		tags: ['Plugin - Pterodactyl API - Servers'],
		parameters: [
			new OA\Parameter(
				name: 'serverId',
				description: 'The UUID or ID of the server',
				in: 'path',
				required: true,
				schema: new OA\Schema(type: 'string')
			),
			new OA\Parameter(
				name: 'include',
				description: 'Comma-separated list of relationships to include (user, subusers, allocations, nest, egg, variables)',
				in: 'query',
				required: false,
				schema: new OA\Schema(type: 'string', enum: ['user', 'subusers', 'allocations', 'nest', 'egg', 'variables'])
			)
		],
		responses: [
			new OA\Response(
				response: 200,
				description: 'Server details retrieved successfully',
				content: new OA\JsonContent(
					type: 'object',
					properties: [
						new OA\Property(property: 'object', type: 'string', example: 'server'),
						new OA\Property(
							property: 'attributes',
							type: 'object',
							properties: [
								new OA\Property(property: 'id', type: 'integer'),
								new OA\Property(property: 'external_id', type: 'string'),
								new OA\Property(property: 'uuid', type: 'string'),
								new OA\Property(property: 'identifier', type: 'string'),
								new OA\Property(property: 'name', type: 'string'),
								new OA\Property(property: 'description', type: 'string'),
								new OA\Property(property: 'suspended', type: 'boolean'),
								new OA\Property(property: 'limits', type: 'object'),
								new OA\Property(property: 'feature_limits', type: 'object'),
								new OA\Property(property: 'user', type: 'integer'),
								new OA\Property(property: 'node', type: 'integer'),
								new OA\Property(property: 'allocation', type: 'integer'),
								new OA\Property(property: 'nest', type: 'integer'),
								new OA\Property(property: 'egg', type: 'integer'),
								new OA\Property(property: 'container', type: 'object'),
								new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
								new OA\Property(property: 'updated_at', type: 'string', format: 'date-time')
							]
						)
					]
				)
			),
			new OA\Response(response: 404, description: 'Server not found'),
			new OA\Response(response: 500, description: 'Internal server error')
		]
	)]
	public function show(Request $request, $serverId)
	{
		$include = $request->get('include', '');
		$includeServers = !empty($include);

		$pdo = App::getInstance(true)->getDatabase()->getPdo();

		// Build the main query with JOINs for environment variables (always included)
		$sql = "SELECT 
			s.id,
			s.external_id,
			s.uuid,
			s.uuidShort as identifier,
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
			s.owner_id as user,
			s.node_id as node,
			s.allocation_id as allocation,
			s.realms_id as nest,
			s.spell_id as egg,
			s.startup as startup_command,
			s.image,
			s.installed_at as installed,
			s.created_at,
			s.updated_at,
			sv.variable_value,
			spv.env_variable,
			spv.default_value
		FROM " . self::SERVERS_TABLE . " s
		LEFT JOIN " . self::SERVER_VARIABLES_TABLE . " sv ON s.id = sv.server_id
		LEFT JOIN " . self::SPELL_VARIABLES_TABLE . " spv ON sv.variable_id = spv.id
		WHERE s.id = :server_id";

		$stmt = $pdo->prepare($sql);
		$stmt->bindValue(':server_id', $serverId, \PDO::PARAM_INT);
		$stmt->execute();
		$results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

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

		// Get all default variables for the server's egg (to handle duplicates)
		foreach ($servers as $serverId => &$server) {
			if (isset($server['egg'])) {
				$defaultVarsSql = "SELECT env_variable, default_value 
					FROM " . self::SPELL_VARIABLES_TABLE . " 
					WHERE spell_id = :spell_id 
					AND id IN (
						SELECT MAX(id) 
						FROM " . self::SPELL_VARIABLES_TABLE . " 
						WHERE spell_id = :spell_id 
						GROUP BY env_variable
					)";
				$defaultStmt = $pdo->prepare($defaultVarsSql);
				$defaultStmt->bindValue(':spell_id', $server['egg'], \PDO::PARAM_INT);
				$defaultStmt->execute();
				$defaultVars = $defaultStmt->fetchAll(\PDO::FETCH_ASSOC);

				foreach ($defaultVars as $var) {
					if ($var['env_variable'] && $var['default_value'] !== null) {
						$server['default_variables'][$var['env_variable']] = $var['default_value'];
					}
				}
			}
		}

		// Process server data
		$server = reset($servers); // Get the first (and only) server
		$processedServer = [
			'object' => 'server',
			'attributes' => [
				'id' => (int) $server['id'],
				'external_id' => $server['external_id'],
				'uuid' => $server['uuid'],
				'identifier' => $server['identifier'],
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
				'user' => (int) $server['user'],
				'node' => (int) $server['node'],
				'allocation' => (int) $server['allocation'],
				'nest' => (int) $server['nest'],
				'egg' => (int) $server['egg'],
				'container' => [
					'startup_command' => $server['startup_command'],
					'image' => $server['image'],
					'installed' => $server['installed'] ? 1 : 0,
					'environment' => $this->buildEnvironmentVariables($server),
				],
				'created_at' => \App\Addons\pterodactylpanelapi\utils\DateTimePtero::format($server['created_at']),
				'updated_at' => \App\Addons\pterodactylpanelapi\utils\DateTimePtero::format($server['updated_at']),
			],
		];

		// Add relationships if include parameter is specified
		if ($includeServers) {
			$relationships = [];
			$processedServer['relationships'] = $this->buildServerRelationships($server, $include, $pdo);
		}

		return ApiResponse::sendManualResponse($processedServer, 200);
	}

	/**
	 * Get Server by External ID - Retrieve server details using an external ID
	 */
	public function showExternal(Request $request, $externalId)
	{
		$include = $request->get('include', '');
		$includeServers = !empty($include);

		$pdo = App::getInstance(true)->getDatabase()->getPdo();

		// Build the main query with JOINs for environment variables (always included)
		$sql = "SELECT 
			s.id,
			s.external_id,
			s.uuid,
			s.uuidShort as identifier,
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
			s.owner_id as user,
			s.node_id as node,
			s.allocation_id as allocation,
			s.realms_id as nest,
			s.spell_id as egg,
			s.startup as startup_command,
			s.image,
			s.installed_at as installed,
			s.created_at,
			s.updated_at,
			sv.variable_value,
			spv.env_variable,
			spv.default_value
		FROM " . self::SERVERS_TABLE . " s
		LEFT JOIN " . self::SERVER_VARIABLES_TABLE . " sv ON s.id = sv.server_id
		LEFT JOIN " . self::SPELL_VARIABLES_TABLE . " spv ON sv.variable_id = spv.id
		WHERE s.external_id = :external_id";

		$stmt = $pdo->prepare($sql);
		$stmt->bindValue(':external_id', $externalId, \PDO::PARAM_STR);
		$stmt->execute();
		$results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

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

		// Get all default variables for the server's egg (to handle duplicates)
		foreach ($servers as $serverId => &$server) {
			if (isset($server['egg'])) {
				$defaultVarsSql = "SELECT env_variable, default_value 
					FROM " . self::SPELL_VARIABLES_TABLE . " 
					WHERE spell_id = :spell_id 
					AND id IN (
						SELECT MAX(id) 
						FROM " . self::SPELL_VARIABLES_TABLE . " 
						WHERE spell_id = :spell_id 
						GROUP BY env_variable
					)";
				$defaultStmt = $pdo->prepare($defaultVarsSql);
				$defaultStmt->bindValue(':spell_id', $server['egg'], \PDO::PARAM_INT);
				$defaultStmt->execute();
				$defaultVars = $defaultStmt->fetchAll(\PDO::FETCH_ASSOC);

				foreach ($defaultVars as $var) {
					if ($var['env_variable'] && $var['default_value'] !== null) {
						$server['default_variables'][$var['env_variable']] = $var['default_value'];
					}
				}
			}
		}

		// Process server data
		$server = reset($servers); // Get the first (and only) server
		$processedServer = [
			'object' => 'server',
			'attributes' => [
				'id' => (int) $server['id'],
				'external_id' => $server['external_id'],
				'uuid' => $server['uuid'],
				'identifier' => $server['identifier'],
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
				'user' => (int) $server['user'],
				'node' => (int) $server['node'],
				'allocation' => (int) $server['allocation'],
				'nest' => (int) $server['nest'],
				'egg' => (int) $server['egg'],
				'container' => [
					'startup_command' => $server['startup_command'],
					'image' => $server['image'],
					'installed' => $server['installed'] ? 1 : 0,
					'environment' => $this->buildEnvironmentVariables($server),
				],
				'created_at' => \App\Addons\pterodactylpanelapi\utils\DateTimePtero::format($server['created_at']),
				'updated_at' => \App\Addons\pterodactylpanelapi\utils\DateTimePtero::format($server['updated_at']),
			],
		];

		// Add relationships if include parameter is specified
		if ($includeServers) {
			$relationships = [];
			$processedServer['relationships'] = $this->buildServerRelationships($server, $include, $pdo);
		}

		return ApiResponse::sendManualResponse($processedServer, 200);
	}

	/**
	 * Create New Server - Create a new server in the panel
	 */
	public function store(Request $request)
	{
		$data = json_decode($request->getContent(), true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'ValidationException',
						'status' => '422',
						'detail' => 'The request data was invalid or malformed.'
					]
				]
			], 422);
		}

		// Required fields for server creation
		$requiredFields = ['name', 'user', 'egg', 'limits', 'feature_limits', 'allocation'];
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
							'source_field' => implode(', ', $missingFields)
						]
					]
				]
			], 422);
		}

		// Validate limits object
		$requiredLimits = ['memory', 'swap', 'disk', 'io', 'cpu'];
		$missingLimits = [];
		foreach ($requiredLimits as $limit) {
			if (!isset($data['limits'][$limit])) {
				$missingLimits[] = $limit;
			}
		}

		if (!empty($missingLimits)) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'ValidationException',
						'status' => '422',
						'detail' => 'The request data was invalid or malformed.',
						'meta' => [
							'source_field' => 'limits.' . implode(', limits.', $missingLimits)
						]
					]
				]
			], 422);
		}

		// Validate feature_limits object
		$requiredFeatureLimits = ['databases', 'allocations', 'backups'];
		$missingFeatureLimits = [];
		foreach ($requiredFeatureLimits as $limit) {
			if (!isset($data['feature_limits'][$limit])) {
				$missingFeatureLimits[] = $limit;
			}
		}

		if (!empty($missingFeatureLimits)) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'ValidationException',
						'status' => '422',
						'detail' => 'The request data was invalid or malformed.',
						'meta' => [
							'source_field' => 'feature_limits.' . implode(', feature_limits.', $missingFeatureLimits)
						]
					]
				]
			], 422);
		}

		// Validate allocation object
		if (!isset($data['allocation']['default'])) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'ValidationException',
						'status' => '422',
						'detail' => 'The request data was invalid or malformed.',
						'meta' => [
							'source_field' => 'allocation.default'
						]
					]
				]
			], 422);
		}

		// Validate user exists
		$owner = User::getUserById($data['user']);
		if (!$owner) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'ValidationException',
						'status' => '422',
						'detail' => 'The request data was invalid or malformed.',
						'meta' => [
							'source_field' => 'user'
						]
					]
				]
			], 422);
		}

		// Validate spell (egg) exists
		$spell = Spell::getSpellById($data['egg']);
		if (!$spell) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'ValidationException',
						'status' => '422',
						'detail' => 'The request data was invalid or malformed.',
						'meta' => [
							'source_field' => 'egg'
						]
					]
				]
			], 422);
		}

		// Validate allocation exists
		$allocation = Allocation::getAllocationById($data['allocation']['default']);
		if (!$allocation) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'ValidationException',
						'status' => '422',
						'detail' => 'The request data was invalid or malformed.',
						'meta' => [
							'source_field' => 'allocation.default'
						]
					]
				]
			], 422);
		}

		// Check if allocation is already in use
		$existingServer = Server::getServerByAllocationId($data['allocation']['default']);
		if ($existingServer) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'ValidationException',
						'status' => '422',
						'detail' => 'The request data was invalid or malformed.',
						'meta' => [
							'source_field' => 'allocation.default'
						]
					]
				]
			], 422);
		}

		// Get node from allocation
		$node = Node::getNodeById($allocation['node_id']);
		if (!$node) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'ValidationException',
						'status' => '422',
						'detail' => 'The request data was invalid or malformed.',
						'meta' => [
							'source_field' => 'allocation.default'
						]
					]
				]
			], 422);
		}

		// Validate resource limits
		if ($data['limits']['memory'] < 128) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'ValidationException',
						'status' => '422',
						'detail' => 'The request data was invalid or malformed.',
						'meta' => [
							'source_field' => 'limits.memory'
						]
					]
				]
			], 422);
		}
		if ($data['limits']['disk'] < 1024) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'ValidationException',
						'status' => '422',
						'detail' => 'The request data was invalid or malformed.',
						'meta' => [
							'source_field' => 'limits.disk'
						]
					]
				]
			], 422);
		}
		if ($data['limits']['io'] < 10) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'ValidationException',
						'status' => '422',
						'detail' => 'The request data was invalid or malformed.',
						'meta' => [
							'source_field' => 'limits.io'
						]
					]
				]
			], 422);
		}
		if ($data['limits']['cpu'] < 10) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'ValidationException',
						'status' => '422',
						'detail' => 'The request data was invalid or malformed.',
						'meta' => [
							'source_field' => 'limits.cpu'
						]
					]
				]
			], 422);
		}

		// Prepare server data for database
		$serverData = [
			'name' => $data['name'],
			'description' => $data['description'] ?? 'Default description',
			'owner_id' => $data['user'],
			'node_id' => $node['id'],
			'allocation_id' => $data['allocation']['default'],
			'realms_id' => $spell['realm_id'],
			'spell_id' => $data['egg'],
			'memory' => $data['limits']['memory'],
			'swap' => $data['limits']['swap'],
			'disk' => $data['limits']['disk'],
			'io' => $data['limits']['io'],
			'cpu' => $data['limits']['cpu'],
			'threads' => $data['limits']['threads'] ?? null,
			'oom_disabled' => isset($data['limits']['oom_disabled']) ? (int) $data['limits']['oom_disabled'] : 0,
			'allocation_limit' => $data['feature_limits']['allocations'],
			'database_limit' => $data['feature_limits']['databases'],
			'backup_limit' => $data['feature_limits']['backups'],
			'startup' => $data['startup'] ?? $spell['startup'],
			'image' => $data['docker_image'] ?? ($spell['docker_images'] ? json_decode($spell['docker_images'], true)[array_key_first(json_decode($spell['docker_images'], true))] ?? '' : ''),
			'status' => 'installing',
			'skip_scripts' => 0,
			'external_id' => null,
		];

		// Generate UUIDs
		$serverData['uuid'] = \App\Helpers\UUIDUtils::generateV4();
		$serverData['uuidShort'] = substr($serverData['uuid'], 0, 8);

		// Create server in database
		$serverId = Server::createServer($serverData);
		if (!$serverId) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'InternalErrorException',
						'status' => '500',
						'detail' => 'An error occurred while processing this request.'
					]
				]
			], 500);
		}

		// Claim the allocation for this server
		$allocationClaimed = Allocation::assignToServer($data['allocation']['default'], $serverId);
		if (!$allocationClaimed) {
			App::getInstance(true)->getLogger()->error('Failed to claim allocation for server ID: ' . $serverId);
		}

		// Handle environment variables if provided
		if (isset($data['environment']) && is_array($data['environment']) && !empty($data['environment'])) {
			$spellVariables = SpellVariable::getVariablesBySpellId($data['egg']);
			$variables = [];

			foreach ($data['environment'] as $envVariable => $value) {
				// Find the spell variable by env_variable
				$spellVariable = null;
				foreach ($spellVariables as $sv) {
					if ($sv['env_variable'] === $envVariable) {
						$spellVariable = $sv;
						break;
					}
				}

				if ($spellVariable) {
					$variables[] = [
						'variable_id' => $spellVariable['id'],
						'variable_value' => (string) $value,
					];
				}
			}

			if (!empty($variables)) {
				$variablesCreated = ServerVariable::createOrUpdateServerVariables($serverId, $variables);
				if (!$variablesCreated) {
					App::getInstance(true)->getLogger()->error('Failed to create server variables for server ID: ' . $serverId);
				}
			}
		}

		// Create server in Wings
		try {
			$wings = new \App\Services\Wings\Wings(
				$node['fqdn'],
				$node['daemonListen'],
				$node['scheme'],
				$node['daemon_token'],
				30
			);

			$wingsData = [
				'uuid' => $serverData['uuid'],
				'start_on_completion' => true,
			];

			$response = $wings->getServer()->createServer($wingsData);
			if (!$response->isSuccessful()) {
				// Delete the server from database if Wings creation fails
				Server::hardDeleteServer($serverId);
				Allocation::unassignFromServer($data['allocation']['default']);

				return ApiResponse::sendManualResponse([
					'errors' => [
						[
							'code' => 'InternalErrorException',
							'status' => '500',
							'detail' => 'An error occurred while processing this request.'
						]
					]
				], 500);
			}
		} catch (\Exception $e) {
			App::getInstance(true)->getLogger()->error('Failed to create server in Wings: ' . $e->getMessage());
			// Delete the server from database if Wings creation fails
			Server::hardDeleteServer($serverId);
			Allocation::unassignFromServer($data['allocation']['default']);

			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'InternalErrorException',
						'status' => '500',
						'detail' => 'An error occurred while processing this request.'
					]
				]
			], 500);
		}

		// Get the created server with all its data
		$createdServer = Server::getServerById($serverId);
		if (!$createdServer) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'InternalErrorException',
						'status' => '500',
						'detail' => 'An error occurred while processing this request.'
					]
				]
			], 500);
		}

		// Get environment variables for the server
		$pdo = App::getInstance(true)->getDatabase()->getPdo();
		$variablesSql = "SELECT 
			sv.variable_value,
			spv.env_variable,
			spv.default_value
		FROM " . self::SERVER_VARIABLES_TABLE . " sv
		LEFT JOIN " . self::SPELL_VARIABLES_TABLE . " spv ON sv.variable_id = spv.id
		WHERE sv.server_id = :server_id";

		$variablesStmt = $pdo->prepare($variablesSql);
		$variablesStmt->bindValue(':server_id', $serverId, \PDO::PARAM_INT);
		$variablesStmt->execute();
		$variables = $variablesStmt->fetchAll(\PDO::FETCH_ASSOC);

		// Get default variables for the egg
		$defaultVarsSql = "SELECT env_variable, default_value 
			FROM " . self::SPELL_VARIABLES_TABLE . " 
			WHERE spell_id = :spell_id 
			AND id IN (
				SELECT MAX(id) 
				FROM " . self::SPELL_VARIABLES_TABLE . " 
				WHERE spell_id = :spell_id 
				GROUP BY env_variable
			)";
		$defaultStmt = $pdo->prepare($defaultVarsSql);
		$defaultStmt->bindValue(':spell_id', $data['egg'], \PDO::PARAM_INT);
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
		$environment['P_SERVER_UUID'] = $createdServer['uuid'];
		$environment['P_SERVER_ALLOCATION_LIMIT'] = (string) $data['feature_limits']['allocations'];

		// Add location info if available
		$location = Location::getById($node['location_id']);
		if ($location) {
			$environment['P_SERVER_LOCATION'] = $location['name'];
		}

		// Log activity (use server owner's UUID since this is API call)
		Activity::createActivity([
			'user_uuid' => $owner['uuid'],
			'name' => 'create_server',
			'context' => 'Created a new server ' . $data['name'] . ' via API',
			'ip_address' => CloudFlareRealIP::getRealIP(),
		]);

		// Send email notification
		$config = App::getInstance(true)->getConfig();
		try {
			ServerCreated::send([
				'email' => $owner['email'],
				'subject' => 'New server created on ' . $config->getSetting(ConfigInterface::APP_NAME, 'FeatherPanel'),
				'app_name' => $config->getSetting(ConfigInterface::APP_NAME, 'FeatherPanel'),
				'app_url' => $config->getSetting(ConfigInterface::APP_URL, 'featherpanel.mythical.systems'),
				'first_name' => $owner['first_name'],
				'last_name' => $owner['last_name'],
				'username' => $owner['username'],
				'app_support_url' => $config->getSetting(ConfigInterface::APP_SUPPORT_URL, 'https://discord.mythical.systems'),
				'uuid' => $owner['uuid'],
				'enabled' => $config->getSetting(ConfigInterface::SMTP_ENABLED, 'false'),
				'server_name' => $data['name'],
				'server_ip' => $allocation['ip'] . ':' . $allocation['port'],
				'panel_url' => $config->getSetting(ConfigInterface::APP_URL, 'featherpanel.mythical.systems') . '/dashboard',
			]);
		} catch (\Exception $e) {
			App::getInstance(true)->getLogger()->error('Failed to send server created email: ' . $e->getMessage());
			// Note: We don't fail the server creation if email fails, just log the error
		}

		// Format response
		$responseData = [
			'object' => 'server',
			'attributes' => [
				'id' => (int) $createdServer['id'],
				'external_id' => $createdServer['external_id'],
				'uuid' => $createdServer['uuid'],
				'identifier' => $createdServer['uuidShort'],
				'name' => $createdServer['name'],
				'description' => $createdServer['description'],
				'status' => $createdServer['status'],
				'suspended' => (bool) $createdServer['suspended'],
				'limits' => [
					'memory' => (int) $createdServer['memory'],
					'swap' => (int) $createdServer['swap'],
					'disk' => (int) $createdServer['disk'],
					'io' => (int) $createdServer['io'],
					'cpu' => (int) $createdServer['cpu'],
					'threads' => $createdServer['threads'] ? (int) $createdServer['threads'] : null,
					'oom_disabled' => (bool) $createdServer['oom_disabled'],
				],
				'feature_limits' => [
					'databases' => (int) $createdServer['database_limit'],
					'allocations' => (int) $createdServer['allocation_limit'],
					'backups' => (int) $createdServer['backup_limit'],
				],
				'user' => (int) $createdServer['owner_id'],
				'node' => (int) $createdServer['node_id'],
				'allocation' => (int) $createdServer['allocation_id'],
				'nest' => (int) $createdServer['realms_id'],
				'egg' => (int) $createdServer['spell_id'],
				'container' => [
					'startup_command' => $createdServer['startup'],
					'image' => $createdServer['image'],
					'installed' => $createdServer['installed_at'] ? 1 : 0,
					'environment' => $environment,
				],
				'created_at' => \App\Addons\pterodactylpanelapi\utils\DateTimePtero::format($createdServer['created_at']),
				'updated_at' => \App\Addons\pterodactylpanelapi\utils\DateTimePtero::format($createdServer['updated_at']),
			],
		];

		return ApiResponse::sendManualResponse($responseData, 201);
	}

	/**
	 * Suspend Server - Suspend a server to prevent it from starting
	 */
	public function suspend(Request $request, $serverId)
	{
		$server = Server::getServerById($serverId);
		if (!$server) {
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

		$ok = Server::updateServerById($serverId, ['suspended' => 1]);
		if (!$ok) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'InternalErrorException',
						'status' => '500',
						'detail' => 'An error occurred while processing this request.'
					]
				]
			], 500);
		}

		$config = App::getInstance(true)->getConfig();
		$owner = User::getUserById($server['owner_id']);

		// Log activity (use server owner's UUID since this is API call)
		Activity::createActivity([
			'user_uuid' => $owner['uuid'],
			'name' => 'suspend_server',
			'context' => 'Suspended server ' . $server['name'] . ' via API',
			'ip_address' => CloudFlareRealIP::getRealIP(),
		]);

		// Kill server in Wings
		$nodeInfo = Node::getNodeById($server['node_id']);
		$scheme = $nodeInfo['scheme'];
		$host = $nodeInfo['fqdn'];
		$port = $nodeInfo['daemonListen'];
		$token = $nodeInfo['daemon_token'];

		$timeout = (int) 30;
		try {
			$wings = new \App\Services\Wings\Wings(
				$host,
				$port,
				$scheme,
				$token,
				$timeout
			);

			$response = $wings->getServer()->killServer($server['uuid']);
			if (!$response->isSuccessful()) {
				App::getInstance(true)->getLogger()->error('Failed to kill server in Wings: ' . $response->getError());
			}
		} catch (\Exception $e) {
			App::getInstance(true)->getLogger()->error('Failed to kill server in Wings: ' . $e->getMessage());
		}

		// Send email notification
		try {
			ServerBanned::send([
				'email' => $owner['email'],
				'subject' => 'Server suspended on ' . $config->getSetting(ConfigInterface::APP_NAME, 'FeatherPanel'),
				'app_name' => $config->getSetting(ConfigInterface::APP_NAME, 'FeatherPanel'),
				'app_url' => $config->getSetting(ConfigInterface::APP_URL, 'featherpanel.mythical.systems'),
				'first_name' => $owner['first_name'],
				'last_name' => $owner['last_name'],
				'username' => $owner['username'],
				'app_support_url' => $config->getSetting(ConfigInterface::APP_SUPPORT_URL, 'https://discord.mythical.systems'),
				'uuid' => $owner['uuid'],
				'enabled' => $config->getSetting(ConfigInterface::SMTP_ENABLED, 'false'),
				'server_name' => $server['name'],
			]);
		} catch (\Exception $e) {
			App::getInstance(true)->getLogger()->error('Failed to send server suspended email: ' . $e->getMessage());
		}

		// Return 204 No Content as per Pterodactyl API spec
		return new Response('', 204);
	}

	/**
	 * Unsuspend Server - Remove suspension from a server to allow it to start
	 */
	public function unsuspend(Request $request, $serverId)
	{
		$server = Server::getServerById($serverId);
		if (!$server) {
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

		$ok = Server::updateServerById($serverId, ['suspended' => 0]);
		if (!$ok) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'InternalErrorException',
						'status' => '500',
						'detail' => 'An error occurred while processing this request.'
					]
				]
			], 500);
		}

		$config = App::getInstance(true)->getConfig();
		$owner = User::getUserById($server['owner_id']);

		// Log activity (use server owner's UUID since this is API call)
		Activity::createActivity([
			'user_uuid' => $owner['uuid'],
			'name' => 'unsuspend_server',
			'context' => 'Unsuspended server ' . $server['name'] . ' via API',
			'ip_address' => CloudFlareRealIP::getRealIP(),
		]);

		// Send email notification
		try {
			ServerUnbanned::send([
				'email' => $owner['email'],
				'subject' => 'Server unsuspended on ' . $config->getSetting(ConfigInterface::APP_NAME, 'FeatherPanel'),
				'app_name' => $config->getSetting(ConfigInterface::APP_NAME, 'FeatherPanel'),
				'app_url' => $config->getSetting(ConfigInterface::APP_URL, 'featherpanel.mythical.systems'),
				'first_name' => $owner['first_name'],
				'last_name' => $owner['last_name'],
				'username' => $owner['username'],
				'app_support_url' => $config->getSetting(ConfigInterface::APP_SUPPORT_URL, 'https://discord.mythical.systems'),
				'uuid' => $owner['uuid'],
				'enabled' => $config->getSetting(ConfigInterface::SMTP_ENABLED, 'false'),
				'server_name' => $server['name'],
			]);
		} catch (\Exception $e) {
			App::getInstance(true)->getLogger()->error('Failed to send server unsuspended email: ' . $e->getMessage());
		}

		// Return 204 No Content as per Pterodactyl API spec
		return new Response('', 204);
	}

	/**
	 * Reinstall Server - Reinstall a server from its egg configuration
	 */
	public function reinstall(Request $request, $serverId)
	{
		$server = Server::getServerById($serverId);
		if (!$server) {
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

		// Log activity (use server owner's UUID since this is API call)
		$owner = User::getUserById($server['owner_id']);
		Activity::createActivity([
			'user_uuid' => $owner['uuid'],
			'name' => 'reinstall_server',
			'context' => 'Reinstalled server ' . $server['name'] . ' via API',
			'ip_address' => CloudFlareRealIP::getRealIP(),
		]);

		// Trigger reinstall in Wings
		$nodeInfo = Node::getNodeById($server['node_id']);
		$scheme = $nodeInfo['scheme'];
		$host = $nodeInfo['fqdn'];
		$port = $nodeInfo['daemonListen'];
		$token = $nodeInfo['daemon_token'];

		$timeout = (int) 30;
		try {
			$wings = new \App\Services\Wings\Wings(
				$host,
				$port,
				$scheme,
				$token,
				$timeout
			);

			$response = $wings->getServer()->reinstallServer($server['uuid']);
			if (!$response->isSuccessful()) {
				App::getInstance(true)->getLogger()->error('Failed to reinstall server in Wings: ' . $response->getError());
			}
		} catch (\Exception $e) {
			App::getInstance(true)->getLogger()->error('Failed to reinstall server in Wings: ' . $e->getMessage());
		}

		// Return 204 No Content as per Pterodactyl API spec
		return new Response('', 204);
	}

	/**
	 * Delete Server - Delete a server from the panel
	 */
	public function destroy(Request $request, $serverId)
	{
		$server = Server::getServerById($serverId);
		if (!$server) {
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

		// Check if server is running and force parameter is not set
		$force = $request->query->get('force', false);
		if (!$force && $server['status'] === 'running') {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'ValidationException',
						'status' => '422',
						'detail' => 'Cannot delete a running server without force parameter.'
					]
				]
			], 422);
		}

		// Unclaim the allocation before deleting the server
		if (isset($server['allocation_id'])) {
			$allocationUnclaimed = Allocation::unassignFromServer($server['allocation_id']);
			if (!$allocationUnclaimed) {
				App::getInstance(true)->getLogger()->error('Failed to unclaim allocation for server ID: ' . $serverId);
			}
		}

		$config = App::getInstance(true)->getConfig();
		$user = User::getUserById($server['owner_id']);

		$deleted = Server::hardDeleteServer($serverId);
		if (!$deleted) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'InternalErrorException',
						'status' => '500',
						'detail' => 'An error occurred while processing this request.'
					]
				]
			], 500);
		}

		// Delete server from Wings
		$nodeInfo = Node::getNodeById($server['node_id']);
		$scheme = $nodeInfo['scheme'];
		$host = $nodeInfo['fqdn'];
		$port = $nodeInfo['daemonListen'];
		$token = $nodeInfo['daemon_token'];

		$timeout = (int) 30;
		try {
			$wings = new \App\Services\Wings\Wings(
				$host,
				$port,
				$scheme,
				$token,
				$timeout
			);

			$response = $wings->getServer()->deleteServer($server['uuid']);
			if (!$response->isSuccessful()) {
				App::getInstance(true)->getLogger()->error('Failed to delete server in Wings: ' . $response->getError());
			}
		} catch (\Exception $e) {
			App::getInstance(true)->getLogger()->error('Failed to delete server in Wings: ' . $e->getMessage());
		}

		// Log activity (use server owner's UUID since this is API call)
		$owner = User::getUserById($server['owner_id']);
		Activity::createActivity([
			'user_uuid' => $owner['uuid'],
			'name' => 'delete_server',
			'context' => 'Deleted server ' . $server['name'] . ' via API',
			'ip_address' => CloudFlareRealIP::getRealIP(),
		]);

		// Send email notification
		try {
			ServerDeleted::send([
				'email' => $owner['email'],
				'subject' => 'Server deleted on ' . $config->getSetting(ConfigInterface::APP_NAME, 'FeatherPanel'),
				'app_name' => $config->getSetting(ConfigInterface::APP_NAME, 'FeatherPanel'),
				'app_url' => $config->getSetting(ConfigInterface::APP_URL, 'featherpanel.mythical.systems'),
				'first_name' => $owner['first_name'],
				'last_name' => $owner['last_name'],
				'username' => $owner['username'],
				'app_support_url' => $config->getSetting(ConfigInterface::APP_SUPPORT_URL, 'https://discord.mythical.systems'),
				'uuid' => $owner['uuid'],
				'enabled' => $config->getSetting(ConfigInterface::SMTP_ENABLED, 'false'),
				'server_name' => $server['name'],
				'deletion_time' => date('Y-m-d H:i:s'),
			]);
		} catch (\Exception $e) {
			App::getInstance(true)->getLogger()->error('Failed to send server deleted email: ' . $e->getMessage());
		}

		// Return 204 No Content as per Pterodactyl API spec
		return new Response('', 204);
	}

	/**
	 * Build server relationships (extracted from index method for reuse)
	 */
	private function buildServerRelationships($server, $include, $pdo)
	{
		$relationships = [];

		// Include user if requested
		if (strpos($include, 'user') !== false) {
			$user = User::getUserById($server['user']);
			if ($user) {
				$relationships['user'] = [
					'object' => 'user',
					'attributes' => [
						'id' => (int) $user['id'],
						'external_id' => (string) $user['external_id'],
						'uuid' => $user['uuid'],
						'username' => $user['username'],
						'email' => $user['email'],
						'first_name' => $user['first_name'],
						'last_name' => $user['last_name'],
						'language' => 'en',
						'root_admin' => (bool) ($user['role_id'] == 4),
						'2fa' => (bool) $user['two_fa_enabled'],
						'created_at' => DateTimePtero::format($user['first_seen']),
						'updated_at' => DateTimePtero::format($user['last_seen']),
					],
				];
			}
		}

		// Include node if requested
		if (strpos($include, 'node') !== false) {
			$node = Node::getNodeById($server['node']);
			if ($node) {
				$relationships['node'] = [
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
		}

		// Include allocations if requested
		if (strpos($include, 'allocations') !== false) {
			$allocations = Allocation::getAll(null, null, (int) $server['id'], 1000, 0);
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
			if (!empty($allocationData)) {
				$relationships['allocations'] = [
					'object' => 'list',
					'data' => $allocationData,
				];
			}
		}

		// Include subusers if requested (always include like Pterodactyl)
		if (strpos($include, 'subusers') !== false || true) {
			$subusers = Subuser::getSubusersByServerId((int) $server['id']);
			$subuserData = [];
			foreach ($subusers as $subuser) {
				$subuserData[] = [
					'object' => 'subuser',
					'attributes' => [
						'id' => (int) $subuser['id'],
						'user_id' => (int) $subuser['user_id'],
						'server_id' => (int) $subuser['server_id'],
						'permissions' => $subuser['permissions'] ? json_decode($subuser['permissions'], true) : [],
						'created_at' => DateTimePtero::format($subuser['created_at']),
						'updated_at' => DateTimePtero::format($subuser['updated_at']),
					],
				];
			}
			// Always include subusers (even if empty, like Pterodactyl)
			$relationships['subusers'] = [
				'object' => 'list',
				'data' => $subuserData,
			];
		}

		// Include nest (realm) if requested
		if (strpos($include, 'nest') !== false) {
			$realm = Realm::getById($server['nest']);
			if ($realm) {
				$relationships['nest'] = [
					'object' => 'nest',
					'attributes' => [
						'id' => (int) $realm['id'],
						'uuid' => $realm['uuid'] ?? null,
						'author' => $realm['author'],
						'name' => $realm['name'],
						'description' => $realm['description'],
						'created_at' => DateTimePtero::format($realm['created_at']),
						'updated_at' => DateTimePtero::format($realm['updated_at']),
					],
				];
			}
		}

		// Include egg (spell) if requested
		if (strpos($include, 'egg') !== false) {
			$spell = Spell::getSpellById($server['egg']);
			if ($spell) {
				$relationships['egg'] = [
					'object' => 'egg',
					'attributes' => [
						'id' => (int) $spell['id'],
						'uuid' => $spell['uuid'] ?? null,
						'name' => $spell['name'],
						'nest' => (int) $spell['realm_id'],
						'author' => $spell['author'],
						'description' => $spell['description'],
						'docker_image' => $spell['docker_images'] ? json_decode($spell['docker_images'], true)[array_key_first(json_decode($spell['docker_images'], true))] ?? '' : '',
						'docker_images' => $spell['docker_images'] ? json_decode($spell['docker_images'], true) : [],
						'config' => [
							'files' => $spell['config_files'] ? json_decode($spell['config_files'], true) : [],
							'startup' => $spell['config_startup'] ? json_decode($spell['config_startup'], true) : [],
							'stop' => $spell['config_stop'] ?? '',
							'logs' => $spell['config_logs'] ? json_decode($spell['config_logs'], true) : [],
							'file_denylist' => $spell['file_denylist'] ? json_decode($spell['file_denylist'], true) : [],
							'extends' => $spell['config_from'] ? (int) $spell['config_from'] : null,
						],
						'startup' => $spell['startup'] ?? '',
						'script' => [
							'privileged' => (bool) $spell['script_is_privileged'],
							'install' => $spell['script_install'] ?? '',
							'entry' => $spell['script_entry'] ?? 'ash',
							'container' => $spell['script_container'] ?? 'alpine:3.4',
							'extends' => $spell['copy_script_from'] ? (int) $spell['copy_script_from'] : null,
						],
						'created_at' => DateTimePtero::format($spell['created_at']),
						'updated_at' => DateTimePtero::format($spell['updated_at']),
					],
				];
			}
		}

		// Include variables as separate relationship if requested
		if (strpos($include, 'variables') !== false) {
			$variablesSql = "SELECT 
				spv.id,
				spv.spell_id as egg_id,
				spv.name,
				spv.description,
				spv.env_variable,
				spv.default_value,
				spv.user_viewable,
				spv.user_editable,
				spv.rules,
				spv.created_at,
				spv.updated_at,
				COALESCE(sv.variable_value, spv.default_value) as server_value
			FROM " . self::SPELL_VARIABLES_TABLE . " spv
			LEFT JOIN " . self::SERVER_VARIABLES_TABLE . " sv ON spv.id = sv.variable_id AND sv.server_id = :server_id
			WHERE spv.spell_id = :spell_id
			AND spv.id IN (
				SELECT MAX(id) 
				FROM " . self::SPELL_VARIABLES_TABLE . " 
				WHERE spell_id = :spell_id 
				GROUP BY env_variable
			)
			ORDER BY spv.id";

			$variablesStmt = $pdo->prepare($variablesSql);
			$variablesStmt->bindValue(':server_id', $server['id'], \PDO::PARAM_INT);
			$variablesStmt->bindValue(':spell_id', $server['egg'], \PDO::PARAM_INT);
			$variablesStmt->execute();
			$variables = $variablesStmt->fetchAll(\PDO::FETCH_ASSOC);

			$variableData = [];
			foreach ($variables as $variable) {
				$variableData[] = [
					'object' => 'server_variable',
					'attributes' => [
						'id' => (int) $variable['id'],
						'egg_id' => (int) $variable['egg_id'],
						'name' => $variable['name'],
						'description' => $variable['description'],
						'env_variable' => $variable['env_variable'],
						'default_value' => $variable['default_value'],
						'user_viewable' => (bool) $variable['user_viewable'],
						'user_editable' => (bool) $variable['user_editable'],
						'rules' => $variable['rules'],
						'created_at' => DateTimePtero::format($variable['created_at']),
						'updated_at' => DateTimePtero::format($variable['updated_at']),
						'server_value' => $variable['server_value'],
					],
				];
			}
			if (!empty($variableData)) {
				$relationships['variables'] = [
					'object' => 'list',
					'data' => $variableData,
				];
			}
		}

		// Include location if requested
		if (strpos($include, 'location') !== false) {
			// Get location from node
			$node = Node::getNodeById($server['node']);
			if ($node && isset($node['location_id'])) {
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
		}

		// Include databases if requested (always include like Pterodactyl)
		if (strpos($include, 'databases') !== false || true) {
			$databases = ServerDatabase::getDatabasesByServerId((int) $server['id']);
			$databaseData = [];
			foreach ($databases as $database) {
				$databaseData[] = [
					'object' => 'databases',
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
			}
			// Always include databases (even if empty, like Pterodactyl)
			$relationships['databases'] = [
				'object' => 'list',
				'data' => $databaseData,
			];
		}

		// Include backups if requested
		if (strpos($include, 'backups') !== false) {
			$backups = Backup::getBackupsByServerId((int) $server['id']);
			$backupData = [];
			foreach ($backups as $backup) {
				$backupData[] = [
					'object' => 'backup',
					'attributes' => [
						'id' => (int) $backup['id'],
						'uuid' => $backup['uuid'],
						'name' => $backup['name'],
						'ignored_files' => $backup['ignored_files'] ? json_decode($backup['ignored_files'], true) : [],
						'is_successful' => (bool) $backup['is_successful'],
						'bytes' => (int) $backup['bytes'],
						'created_at' => DateTimePtero::format($backup['created_at']),
						'completed_at' => $backup['completed_at'] ? DateTimePtero::format($backup['completed_at']) : null,
					],
				];
			}
			if (!empty($backupData)) {
				$relationships['backups'] = [
					'object' => 'list',
					'data' => $backupData,
				];
			}
		}

		return $relationships;
	}

	/**
	 * Build environment variables like Pterodactyl does
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
		if (isset($server['node'])) {
			$node = Node::getNodeById($server['node']);
			if ($node && isset($node['location_id'])) {
				$location = Location::getById($node['location_id']);
				if ($location) {
					$environment['P_SERVER_LOCATION'] = $location['name'];
				}
			}
		}

		return $environment;
	}

	/**
	 * Update server details (name, user, external_id, description)
	 */
	public function updateDetails(Request $request, int $serverId): Response
	{
		$server = Server::getServerById($serverId);
		if (!$server) {
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

		$data = json_decode($request->getContent(), true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'ValidationException',
						'status' => '422',
						'detail' => 'The request data was invalid or malformed.',
						'meta' => [
							'source_field' => 'request_body'
						]
					]
				]
			], 422);
		}

		// Prepare update data
		$updateData = [];

		if (isset($data['name'])) {
			if (empty($data['name']) || strlen($data['name']) < 1) {
				return ApiResponse::sendManualResponse([
					'errors' => [
						[
							'code' => 'ValidationException',
							'status' => '422',
							'detail' => 'The request data was invalid or malformed.',
							'meta' => [
								'source_field' => 'name'
							]
						]
					]
				], 422);
			}
			$updateData['name'] = $data['name'];
		}

		if (isset($data['description'])) {
			$updateData['description'] = $data['description'];
		}

		if (isset($data['external_id'])) {
			$updateData['external_id'] = $data['external_id'];
		}

		if (isset($data['user'])) {
			// Validate user exists
			$user = User::getUserById($data['user']);
			if (!$user) {
				return ApiResponse::sendManualResponse([
					'errors' => [
						[
							'code' => 'ValidationException',
							'status' => '422',
							'detail' => 'The request data was invalid or malformed.',
							'meta' => [
								'source_field' => 'user'
							]
						]
					]
				], 422);
			}
			$updateData['owner_id'] = $data['user'];
		}

		if (empty($updateData)) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'ValidationException',
						'status' => '422',
						'detail' => 'The request data was invalid or malformed.',
						'meta' => [
							'source_field' => 'request_body'
						]
					]
				]
			], 422);
		}

		// Update server
		$pdo = App::getInstance(true)->getDatabase()->getPdo();
		$setParts = [];
		$params = ['id' => $serverId];

		foreach ($updateData as $field => $value) {
			$setParts[] = "`{$field}` = :{$field}";
			$params[$field] = $value;
		}

		$sql = "UPDATE `" . self::SERVERS_TABLE . "` SET " . implode(', ', $setParts) . " WHERE `id` = :id";
		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);

		// Get updated server data
		$updatedServer = Server::getServerById($serverId);

		// Log activity
		$owner = User::getUserById($updatedServer['owner_id']);
		Activity::createActivity([
			'user_uuid' => $owner['uuid'],
			'name' => 'update_server_details',
			'context' => 'Updated server details for ' . $updatedServer['name'] . ' via API',
			'ip_address' => CloudFlareRealIP::getRealIP(),
		]);

		// Return server data in Pterodactyl format
		return $this->formatServerResponse($updatedServer, $request);
	}

	/**
	 * Update server build configuration (limits, allocations)
	 */
	public function updateBuild(Request $request, int $serverId): Response
	{
		$server = Server::getServerById($serverId);
		if (!$server) {
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

		$data = json_decode($request->getContent(), true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'ValidationException',
						'status' => '422',
						'detail' => 'The request data was invalid or malformed.',
						'meta' => [
							'source_field' => 'request_body'
						]
					]
				]
			], 422);
		}

		// Validate required fields
		$requiredFields = ['allocation', 'memory', 'swap', 'disk', 'io', 'cpu', 'feature_limits'];
		foreach ($requiredFields as $field) {
			if (!isset($data[$field])) {
				return ApiResponse::sendManualResponse([
					'errors' => [
						[
							'code' => 'ValidationException',
							'status' => '422',
							'detail' => 'The request data was invalid or malformed.',
							'meta' => [
								'source_field' => $field
							]
						]
					]
				], 422);
			}
		}

		// Validate feature_limits
		$requiredFeatureLimits = ['databases', 'allocations', 'backups'];
		foreach ($requiredFeatureLimits as $field) {
			if (!isset($data['feature_limits'][$field])) {
				return ApiResponse::sendManualResponse([
					'errors' => [
						[
							'code' => 'ValidationException',
							'status' => '422',
							'detail' => 'The request data was invalid or malformed.',
							'meta' => [
								'source_field' => 'feature_limits.' . $field
							]
						]
					]
				], 422);
			}
		}

		// Validate allocation exists
		$allocation = Allocation::getAllocationById($data['allocation']);
		if (!$allocation) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'ValidationException',
						'status' => '422',
						'detail' => 'The request data was invalid or malformed.',
						'meta' => [
							'source_field' => 'allocation'
						]
					]
				]
			], 422);
		}

		// Check if allocation is already in use by another server
		if ($allocation['server_id'] !== null && $allocation['server_id'] != $serverId) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'ValidationException',
						'status' => '422',
						'detail' => 'The request data was invalid or malformed.',
						'meta' => [
							'source_field' => 'allocation'
						]
					]
				]
			], 422);
		}

		// Validate limits
		if ($data['memory'] < 128) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'ValidationException',
						'status' => '422',
						'detail' => 'The request data was invalid or malformed.',
						'meta' => [
							'source_field' => 'memory'
						]
					]
				]
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
							'source_field' => 'disk'
						]
					]
				]
			], 422);
		}

		// Prepare update data
		$updateData = [
			'allocation_id' => $data['allocation'],
			'memory' => $data['memory'],
			'swap' => $data['swap'],
			'disk' => $data['disk'],
			'io' => $data['io'],
			'cpu' => $data['cpu'],
			'threads' => $data['threads'] ?? null,
			'oom_disabled' => isset($data['oom_disabled']) ? (int) $data['oom_disabled'] : 0,
			'allocation_limit' => $data['feature_limits']['allocations'],
			'database_limit' => $data['feature_limits']['databases'],
			'backup_limit' => $data['feature_limits']['backups'],
		];

		// Update server
		$pdo = App::getInstance(true)->getDatabase()->getPdo();
		$setParts = [];
		$params = ['id' => $serverId];

		foreach ($updateData as $field => $value) {
			$setParts[] = "`{$field}` = :{$field}";
			$params[$field] = $value;
		}

		$sql = "UPDATE `" . self::SERVERS_TABLE . "` SET " . implode(', ', $setParts) . " WHERE `id` = :id";
		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);

		// Handle allocation changes
		if ($data['allocation'] != $server['allocation_id']) {
			// Unassign old allocation
			Allocation::unassignFromServer($server['allocation_id']);
			// Assign new allocation
			Allocation::assignToServer($data['allocation'], $serverId);
		}

		// Handle additional allocations if provided
		if (isset($data['add_allocations']) && is_array($data['add_allocations'])) {
			foreach ($data['add_allocations'] as $allocationId) {
				$alloc = Allocation::getAllocationById($allocationId);
				if ($alloc && $alloc['server_id'] === null) {
					Allocation::assignToServer($allocationId, $serverId);
				}
			}
		}

		// Handle removal of allocations if provided
		if (isset($data['remove_allocations']) && is_array($data['remove_allocations'])) {
			foreach ($data['remove_allocations'] as $allocationId) {
				$alloc = Allocation::getAllocationById($allocationId);
				if ($alloc && $alloc['server_id'] == $serverId && $allocationId != $data['allocation']) {
					Allocation::unassignFromServer($allocationId);
				}
			}
		}

		// Get updated server data
		$updatedServer = Server::getServerById($serverId);

		// Log activity
		$owner = User::getUserById($updatedServer['owner_id']);
		Activity::createActivity([
			'user_uuid' => $owner['uuid'],
			'name' => 'update_server_build',
			'context' => 'Updated server build configuration for ' . $updatedServer['name'] . ' via API',
			'ip_address' => CloudFlareRealIP::getRealIP(),
		]);

		// Return server data in Pterodactyl format
		return $this->formatServerResponse($updatedServer, $request);
	}

	/**
	 * Update server startup configuration
	 */
	public function updateStartup(Request $request, int $serverId): Response
	{
		$server = Server::getServerById($serverId);
		if (!$server) {
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

		$data = json_decode($request->getContent(), true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'ValidationException',
						'status' => '422',
						'detail' => 'The request data was invalid or malformed.',
						'meta' => [
							'source_field' => 'request_body'
						]
					]
				]
			], 422);
		}

		// Validate required fields
		$requiredFields = ['startup', 'environment', 'egg'];
		foreach ($requiredFields as $field) {
			if (!isset($data[$field])) {
				return ApiResponse::sendManualResponse([
					'errors' => [
						[
							'code' => 'ValidationException',
							'status' => '422',
							'detail' => 'The request data was invalid or malformed.',
							'meta' => [
								'source_field' => $field
							]
						]
					]
				], 422);
			}
		}

		// Validate egg exists
		$spell = Spell::getSpellById($data['egg']);
		if (!$spell) {
			return ApiResponse::sendManualResponse([
				'errors' => [
					[
						'code' => 'ValidationException',
						'status' => '422',
						'detail' => 'The request data was invalid or malformed.',
						'meta' => [
							'source_field' => 'egg'
						]
					]
				]
			], 422);
		}

		// Prepare update data
		$updateData = [
			'startup' => $data['startup'],
			'spell_id' => $data['egg'],
			'skip_scripts' => isset($data['skip_scripts']) ? (int) $data['skip_scripts'] : 0,
		];

		if (isset($data['image'])) {
			$updateData['image'] = $data['image'];
		}

		// Update server
		$pdo = App::getInstance(true)->getDatabase()->getPdo();
		$setParts = [];
		$params = ['id' => $serverId];

		foreach ($updateData as $field => $value) {
			$setParts[] = "`{$field}` = :{$field}";
			$params[$field] = $value;
		}

		$sql = "UPDATE `" . self::SERVERS_TABLE . "` SET " . implode(', ', $setParts) . " WHERE `id` = :id";
		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);

		// Update server variables
		$pdo = App::getInstance(true)->getDatabase()->getPdo();

		// Get spell variables for this egg
		$spellVariables = SpellVariable::getVariablesBySpellId($data['egg']);

		// Delete existing server variables
		$stmt = $pdo->prepare("DELETE FROM `" . self::SERVER_VARIABLES_TABLE . "` WHERE `server_id` = :server_id");
		$stmt->execute(['server_id' => $serverId]);

		// Insert new server variables
		foreach ($data['environment'] as $envVar => $value) {
			// Find matching spell variable
			$spellVar = null;
			foreach ($spellVariables as $sv) {
				if ($sv['env_variable'] === $envVar) {
					$spellVar = $sv;
					break;
				}
			}

			if ($spellVar) {
				$stmt = $pdo->prepare("INSERT INTO `" . self::SERVER_VARIABLES_TABLE . "` (`server_id`, `variable_id`, `variable_value`) VALUES (:server_id, :variable_id, :variable_value)");
				$stmt->execute([
					'server_id' => $serverId,
					'variable_id' => $spellVar['id'],
					'variable_value' => $value,
				]);
			}
		}

		// Get updated server data
		$updatedServer = Server::getServerById($serverId);

		// Log activity
		$owner = User::getUserById($updatedServer['owner_id']);
		Activity::createActivity([
			'user_uuid' => $owner['uuid'],
			'name' => 'update_server_startup',
			'context' => 'Updated server startup configuration for ' . $updatedServer['name'] . ' via API',
			'ip_address' => CloudFlareRealIP::getRealIP(),
		]);

		// Return server data in Pterodactyl format
		return $this->formatServerResponse($updatedServer, $request);
	}

	/**
	 * Format server response in Pterodactyl API format
	 */
	private function formatServerResponse(array $server, Request $request): Response
	{
		// Get related data - use the actual field names from database
		$owner = User::getUserById($server['owner_id']);
		$node = Node::getNodeById($server['node_id']);
		$realm = Realm::getById($server['realms_id']);
		$spell = Spell::getSpellById($server['spell_id']);
		$allocation = Allocation::getAllocationById($server['allocation_id']);

		// Build environment variables - pass the server data as-is
		$environment = $this->buildEnvironmentVariables($server);

		// Build relationships
		$relationships = [];
		$include = $request->query->get('include', '');

		if (strpos($include, 'user') !== false) {
			$relationships['user'] = [
				'object' => 'user',
				'attributes' => [
					'id' => $owner['id'],
					'external_id' => $owner['external_id'],
					'uuid' => $owner['uuid'],
					'username' => $owner['username'],
					'email' => $owner['email'],
					'first_name' => $owner['first_name'],
					'last_name' => $owner['last_name'],
					'language' => $owner['language'],
					'root_admin' => $owner['role_id'] == 1,
					'2fa' => !empty($owner['two_fa_key']),
					'created_at' => DateTimePtero::format($owner['created_at']),
					'updated_at' => DateTimePtero::format($owner['updated_at']),
				]
			];
		}

		if (strpos($include, 'node') !== false) {
			$relationships['node'] = [
				'object' => 'node',
				'attributes' => [
					'id' => $node['id'],
					'uuid' => $node['uuid'],
					'public' => $node['public'],
					'name' => $node['name'],
					'description' => $node['description'],
					'location_id' => $node['location_id'],
					'fqdn' => $node['fqdn'],
					'scheme' => $node['scheme'],
					'behind_proxy' => $node['behind_proxy'],
					'maintenance_mode' => $node['maintenance_mode'],
					'memory' => $node['memory'],
					'disk' => $node['disk'],
					'upload_size' => $node['upload_size'],
					'daemon_listen' => $node['daemon_listen'],
					'daemon_sftp' => $node['daemon_sftp'],
					'daemon_base' => $node['daemon_base'],
					'created_at' => DateTimePtero::format($node['created_at']),
					'updated_at' => DateTimePtero::format($node['updated_at']),
					'allocated_resources' => [
						'memory' => $node['memory'],
						'disk' => $node['disk'],
					]
				]
			];
		}

		if (strpos($include, 'allocations') !== false) {
			$relationships['allocations'] = [
				'object' => 'list',
				'data' => [
					[
						'object' => 'allocation',
						'attributes' => [
							'id' => $allocation['id'],
							'ip' => $allocation['ip'],
							'port' => $allocation['port'],
							'ip_alias' => $allocation['ip_alias'],
							'notes' => $allocation['notes'],
							'assigned' => $allocation['server_id'] !== null,
							'created_at' => DateTimePtero::format($allocation['created_at']),
							'updated_at' => DateTimePtero::format($allocation['updated_at']),
						]
					]
				]
			];
		}

		if (strpos($include, 'nest') !== false) {
			$relationships['nest'] = [
				'object' => 'nest',
				'attributes' => [
					'id' => $realm['id'],
					'uuid' => $realm['uuid'] ?? null,
					'author' => $realm['author'],
					'name' => $realm['name'],
					'description' => $realm['description'],
					'created_at' => DateTimePtero::format($realm['created_at']),
					'updated_at' => DateTimePtero::format($realm['updated_at']),
				]
			];
		}

		if (strpos($include, 'egg') !== false) {
			$relationships['egg'] = [
				'object' => 'egg',
				'attributes' => [
					'id' => $spell['id'],
					'uuid' => $spell['uuid'] ?? null,
					'name' => $spell['name'],
					'description' => $spell['description'],
					'nest' => $spell['realm_id'],
					'author' => $spell['author'],
					'docker_image' => $spell['docker_images'] ? json_decode($spell['docker_images'], true)[array_key_first(json_decode($spell['docker_images'], true))] ?? '' : '',
					'docker_images' => $spell['docker_images'] ? json_decode($spell['docker_images'], true) : [],
					'config' => [
						'files' => $spell['config_files'] ? json_decode($spell['config_files'], true) : [],
						'startup' => $spell['config_startup'] ? json_decode($spell['config_startup'], true) : [],
						'logs' => $spell['config_logs'] ? json_decode($spell['config_logs'], true) : [],
						'stop' => $spell['config_stop'] ? json_decode($spell['config_stop'], true) : [],
						'file_denylist' => $spell['file_denylist'] ? json_decode($spell['file_denylist'], true) : [],
						'extends' => $spell['config_from'] ? json_decode($spell['config_from'], true) : null,
					],
					'startup' => $spell['startup'],
					'script' => $spell['script'] ? json_decode($spell['script'], true) : null,
					'created_at' => DateTimePtero::format($spell['created_at']),
					'updated_at' => DateTimePtero::format($spell['updated_at']),
				]
			];
		}

		if (strpos($include, 'variables') !== false) {
			$relationships['variables'] = [
				'object' => 'list',
				'data' => []
			];
		}

		$response = [
			'object' => 'server',
			'attributes' => [
				'id' => $server['id'],
				'external_id' => $server['external_id'],
				'uuid' => $server['uuid'],
				'identifier' => $server['uuidShort'] ?? substr($server['uuid'], 0, 8),
				'name' => $server['name'],
				'description' => $server['description'],
				'status' => $server['status'],
				'suspended' => (bool) $server['suspended'],
				'limits' => [
					'memory' => $server['memory'],
					'swap' => $server['swap'],
					'disk' => $server['disk'],
					'io' => $server['io'],
					'cpu' => $server['cpu'],
					'threads' => $server['threads'],
					'oom_disabled' => (bool) $server['oom_disabled'],
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
					'installed' => (int) ($server['installed_at'] ?? 0) !== 0,
					'environment' => $environment,
				],
				'created_at' => DateTimePtero::format($server['created_at']),
				'updated_at' => DateTimePtero::format($server['updated_at']),
			]
		];

		if (!empty($relationships)) {
			$response['relationships'] = $relationships;
		}

		return ApiResponse::sendManualResponse($response, 200);
	}
}
