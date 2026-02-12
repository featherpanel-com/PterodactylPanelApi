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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;
use App\Addons\pterodactylpanelapi\controllers\ApiKeysController;

return function (RouteCollection $routes): void {
    App::getInstance(true)->registerAuthRoute(
        $routes,
        'pterodactylpanelapi-client-api-keys-index',
        '/api/pterodactylpanelapi/client/api-keys',
        function (Request $request) {
            $request->attributes->set('pterodactyl_api_key_type', 'client');
            $request->attributes->set('pterodactyl_api_key_scope', 'self');

            return (new ApiKeysController())->index($request);
        },
        ['GET']
    );

    App::getInstance(true)->registerAuthRoute(
        $routes,
        'pterodactylpanelapi-client-api-keys-show',
        '/api/pterodactylpanelapi/client/api-keys/{id}',
        function (Request $request, array $args) {
            $id = $args['id'] ?? null;
            if ($id === null || !is_numeric($id)) {
                return \App\Helpers\ApiResponse::error('Missing or invalid ID', 'INVALID_ID', 400);
            }

            $request->attributes->set('pterodactyl_api_key_type', 'client');
            $request->attributes->set('pterodactyl_api_key_scope', 'self');

            return (new ApiKeysController())->show($request, (int) $id);
        },
        ['GET']
    );

    App::getInstance(true)->registerAuthRoute(
        $routes,
        'pterodactylpanelapi-client-api-keys-create',
        '/api/pterodactylpanelapi/client/api-keys',
        function (Request $request) {
            $request->attributes->set('pterodactyl_api_key_type', 'client');
            $request->attributes->set('pterodactyl_api_key_scope', 'self');

            return (new ApiKeysController())->create($request);
        },
        ['POST']
    );

    App::getInstance(true)->registerAuthRoute(
        $routes,
        'pterodactylpanelapi-client-api-keys-update',
        '/api/pterodactylpanelapi/client/api-keys/{id}',
        function (Request $request, array $args) {
            $id = $args['id'] ?? null;
            if ($id === null || !is_numeric($id)) {
                return \App\Helpers\ApiResponse::error('Missing or invalid ID', 'INVALID_ID', 400);
            }

            $request->attributes->set('pterodactyl_api_key_type', 'client');
            $request->attributes->set('pterodactyl_api_key_scope', 'self');

            return (new ApiKeysController())->update($request, (int) $id);
        },
        ['PUT', 'PATCH']
    );

    App::getInstance(true)->registerAuthRoute(
        $routes,
        'pterodactylpanelapi-client-api-keys-delete',
        '/api/pterodactylpanelapi/client/api-keys/{id}',
        function (Request $request, array $args) {
            $id = $args['id'] ?? null;
            if ($id === null || !is_numeric($id)) {
                return \App\Helpers\ApiResponse::error('Missing or invalid ID', 'INVALID_ID', 400);
            }

            $request->attributes->set('pterodactyl_api_key_type', 'client');
            $request->attributes->set('pterodactyl_api_key_scope', 'self');

            return (new ApiKeysController())->delete($request, (int) $id);
        },
        ['DELETE']
    );

    App::getInstance(true)->registerAuthRoute(
        $routes,
        'pterodactylpanelapi-client-api-keys-options',
        '/api/pterodactylpanelapi/client/api-keys',
        function (Request $request) {
            return \App\Helpers\ApiResponse::success(null, 'CORS preflight', 200);
        },
        ['OPTIONS']
    );

    App::getInstance(true)->registerAuthRoute(
        $routes,
        'pterodactylpanelapi-client-api-keys-options-id',
        '/api/pterodactylpanelapi/client/api-keys/{id}',
        function (Request $request, array $args) {
            return \App\Helpers\ApiResponse::success(null, 'CORS preflight', 200);
        },
        ['OPTIONS']
    );
};
