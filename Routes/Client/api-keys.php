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
