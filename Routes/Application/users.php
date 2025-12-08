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
