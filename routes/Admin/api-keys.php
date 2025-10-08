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

use App\App;
use App\Permissions;
use App\Addons\pterodactylpanelapi\controllers\ApiKeysController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;

return function (RouteCollection $routes): void {
	// List all API keys (GET)
	App::getInstance(true)->registerAdminRoute(
		$routes,
		'pterodactylpanelapi-api-keys-index',
		'/api/pterodactylpanelapi/api-keys',
		function (Request $request) {
			return (new ApiKeysController())->index($request);
		},
		Permissions::ADMIN_PLUGINS_MANAGE,
		['GET']
	);

	// Get a single API key by ID (GET)
	App::getInstance(true)->registerAdminRoute(
		$routes,
		'pterodactylpanelapi-api-keys-show',
		'/api/pterodactylpanelapi/api-keys/{id}',
		function (Request $request, array $args) {
			$id = $args['id'] ?? null;
			if (!$id || !is_numeric($id)) {
				return \App\Helpers\ApiResponse::error('Missing or invalid ID', 'INVALID_ID', 400);
			}
			return (new ApiKeysController())->show($request, (int) $id);
		},
		Permissions::ADMIN_PLUGINS_MANAGE,
		['GET']
	);

	// Create a new API key (POST)
	App::getInstance(true)->registerAdminRoute(
		$routes,
		'pterodactylpanelapi-api-keys-create',
		'/api/pterodactylpanelapi/api-keys',
		function (Request $request) {
			return (new ApiKeysController())->create($request);
		},
		Permissions::ADMIN_PLUGINS_MANAGE,
		['POST']
	);

	// Update an API key by ID (PUT/PATCH)
	App::getInstance(true)->registerAdminRoute(
		$routes,
		'pterodactylpanelapi-api-keys-update',
		'/api/pterodactylpanelapi/api-keys/{id}',
		function (Request $request, array $args) {
			$id = $args['id'] ?? null;
			if (!$id || !is_numeric($id)) {
				return \App\Helpers\ApiResponse::error('Missing or invalid ID', 'INVALID_ID', 400);
			}
			return (new ApiKeysController())->update($request, (int) $id);
		},
		Permissions::ADMIN_PLUGINS_MANAGE,
		['PUT', 'PATCH']
	);

	// Delete an API key by ID (DELETE)
	App::getInstance(true)->registerAdminRoute(
		$routes,
		'pterodactylpanelapi-api-keys-delete',
		'/api/pterodactylpanelapi/api-keys/{id}',
		function (Request $request, array $args) {
			$id = $args['id'] ?? null;
			if (!$id || !is_numeric($id)) {
				return \App\Helpers\ApiResponse::error('Missing or invalid ID', 'INVALID_ID', 400);
			}
			return (new ApiKeysController())->delete($request, (int) $id);
		},
		Permissions::ADMIN_PLUGINS_MANAGE,
		['DELETE']
	);

	// Handle CORS preflight requests (OPTIONS)
	App::getInstance(true)->registerAdminRoute(
		$routes,
		'pterodactylpanelapi-api-keys-options',
		'/api/pterodactylpanelapi/api-keys',
		function (Request $request) {
			return \App\Helpers\ApiResponse::success(null, 'CORS preflight', 200);
		},
		Permissions::ADMIN_PLUGINS_MANAGE,
		['OPTIONS']
	);

	// Handle CORS preflight requests for individual keys (OPTIONS)
	App::getInstance(true)->registerAdminRoute(
		$routes,
		'pterodactylpanelapi-api-keys-options-id',
		'/api/pterodactylpanelapi/api-keys/{id}',
		function (Request $request, array $args) {
			return \App\Helpers\ApiResponse::success(null, 'CORS preflight', 200);
		},
		Permissions::ADMIN_PLUGINS_MANAGE,
		['OPTIONS']
	);
};
