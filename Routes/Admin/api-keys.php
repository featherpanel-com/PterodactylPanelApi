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

use App\App;
use App\Permissions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;
use App\Addons\pterodactylpanelapi\controllers\ApiKeysController;

return function (RouteCollection $routes): void {
    // List all API keys (GET)
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'pterodactylpanelapi-api-keys-index',
        '/api/pterodactylpanelapi/api-keys',
        function (Request $request) {
            $request->attributes->set('pterodactyl_api_key_type', 'admin');
            $request->attributes->set('pterodactyl_api_key_scope', 'global');

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
            $request->attributes->set('pterodactyl_api_key_type', 'admin');
            $request->attributes->set('pterodactyl_api_key_scope', 'global');

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
            $request->attributes->set('pterodactyl_api_key_type', 'admin');
            $request->attributes->set('pterodactyl_api_key_scope', 'global');

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
            $request->attributes->set('pterodactyl_api_key_type', 'admin');
            $request->attributes->set('pterodactyl_api_key_scope', 'global');

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
            $request->attributes->set('pterodactyl_api_key_type', 'admin');
            $request->attributes->set('pterodactyl_api_key_scope', 'global');

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
