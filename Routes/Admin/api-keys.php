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
