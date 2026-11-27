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

use Symfony\Component\Routing\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;
use App\Addons\pterodactylpanelapi\middleware\PterodactylKeyAuth;
use App\Addons\pterodactylpanelapi\controllers\ApiKeysController;
use App\Addons\pterodactylpanelapi\controllers\client\AccountController;
use App\Addons\pterodactylpanelapi\controllers\client\PermissionsController;

return function (RouteCollection $routes): void {
    $routes->add('pterodactylpanelapi-client-account', new Route(
        '/api/client/account',
        [
            '_controller' => function (Request $request) {
                return (new AccountController())->show($request);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        [], // requirements
        [], // options
        '', // host
        [], // schemes
        ['GET']
    ));

    $routes->add('pterodactylpanelapi-client-account-email', new Route(
        '/api/client/account/email',
        [
            '_controller' => function (Request $request) {
                return (new AccountController())->updateEmail($request);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        [], // requirements
        [], // options
        '', // host
        [], // schemes
        ['PUT']
    ));

    $routes->add('pterodactylpanelapi-client-account-password', new Route(
        '/api/client/account/password',
        [
            '_controller' => function (Request $request) {
                return (new AccountController())->updatePassword($request);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        [], // requirements
        [], // options
        '', // host
        [], // schemes
        ['PUT']
    ));

    $routes->add('pterodactylpanelapi-client-account-api-keys-index', new Route(
        '/api/client/account/api-keys',
        [
            '_controller' => function (Request $request) {
                $request->attributes->set('pterodactyl_api_key_type', 'client');
                $request->attributes->set('pterodactyl_api_key_scope', 'self');

                return (new ApiKeysController())->index($request);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        [],
        [],
        '',
        [],
        ['GET']
    ));

    $routes->add('pterodactylpanelapi-client-account-api-keys-store', new Route(
        '/api/client/account/api-keys',
        [
            '_controller' => function (Request $request) {
                $request->attributes->set('pterodactyl_api_key_type', 'client');
                $request->attributes->set('pterodactyl_api_key_scope', 'self');

                return (new ApiKeysController())->create($request);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        [],
        [],
        '',
        [],
        ['POST']
    ));

    $routes->add('pterodactylpanelapi-client-account-api-keys-delete', new Route(
        '/api/client/account/api-keys/{identifier}',
        [
            '_controller' => function (Request $request, array $parameters) {
                $id = $parameters['identifier'] ?? null;
                if ($id === null || $id === '') {
                    return \App\Helpers\ApiResponse::error('Missing or invalid identifier', 'INVALID_ID', 400);
                }

                $request->attributes->set('pterodactyl_api_key_type', 'client');
                $request->attributes->set('pterodactyl_api_key_scope', 'self');

                return (new ApiKeysController())->delete($request, is_numeric($id) ? (int) $id : (string) $id);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[A-Za-z0-9_\\-]+'],
        [],
        '',
        [],
        ['DELETE']
    ));

    $routes->add('pterodactylpanelapi-client-permissions', new Route(
        '/api/client/permissions',
        [
            '_controller' => function (Request $request) {
                return (new PermissionsController())->index($request);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        [], // requirements
        [], // options
        '', // host
        [], // schemes
        ['GET']
    ));
};
