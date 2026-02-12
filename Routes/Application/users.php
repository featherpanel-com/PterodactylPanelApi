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
use Symfony\Component\Routing\RouteCollection;
use App\Addons\pterodactylpanelapi\middleware\PterodactylKeyAuth;
use App\Addons\pterodactylpanelapi\controllers\application\UsersController;

return function (RouteCollection $routes): void {
    // List all users (GET)
    $routes->add('pterodactylpanelapi-users-index', new Route(
        '/api/application/users',
        [
            '_controller' => function ($request, $parameters) {
                return (new UsersController())->index($request);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        [], // requirements
        [], // options
        '', // host
        [], // schemes
        ['GET']
    ));

    // Get user by ID (GET)
    $routes->add('pterodactylpanelapi-users-show', new Route(
        '/api/application/users/{user}',
        [
            '_controller' => function ($request, $parameters) {
                return (new UsersController())->show($request, $parameters['user']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['user' => '\d+'], // requirements - user must be a digit
        [], // options
        '', // host
        [], // schemes
        ['GET']
    ));

    // Get user by external ID (GET)
    $routes->add('pterodactylpanelapi-users-show-external', new Route(
        '/api/application/users/external/{external_id}',
        [
            '_controller' => function ($request, $parameters) {
                return (new UsersController())->showExternal($request, $parameters['external_id']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        [], // requirements
        [], // options
        '', // host
        [], // schemes
        ['GET']
    ));

    // Create new user (POST)
    $routes->add('pterodactylpanelapi-users-store', new Route(
        '/api/application/users',
        [
            '_controller' => function ($request, $parameters) {
                return (new UsersController())->store($request);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        [], // requirements
        [], // options
        '', // host
        [], // schemes
        ['POST']
    ));

    // Update user (PATCH)
    $routes->add('pterodactylpanelapi-users-update', new Route(
        '/api/application/users/{user}',
        [
            '_controller' => function ($request, $parameters) {
                return (new UsersController())->update($request, $parameters['user']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['user' => '\d+'], // requirements - user must be a digit
        [], // options
        '', // host
        [], // schemes
        ['PATCH']
    ));

    // Delete user (DELETE)
    $routes->add('pterodactylpanelapi-users-destroy', new Route(
        '/api/application/users/{user}',
        [
            '_controller' => function ($request, $parameters) {
                return (new UsersController())->destroy($request, $parameters['user']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['user' => '\d+'], // requirements - user must be a digit
        [], // options
        '', // host
        [], // schemes
        ['DELETE']
    ));
};
