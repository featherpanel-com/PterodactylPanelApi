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
use App\Addons\pterodactylpanelapi\controllers\application\DatabasesController;

return function (RouteCollection $routes) {
    // List server databases (GET)
    $routes->add('pterodactylpanelapi-databases-index', new Route(
        '/api/application/servers/{serverId}/databases',
        [
            '_controller' => function ($request, $parameters) {
                return (new DatabasesController())->index($request, $parameters['serverId']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['serverId' => '[a-zA-Z0-9\-]+'], // requirements - server ID or UUID
        [], // options
        '', // host
        [], // schemes
        ['GET']
    ));

    // Get server database details (GET)
    $routes->add('pterodactylpanelapi-databases-show', new Route(
        '/api/application/servers/{serverId}/databases/{databaseId}',
        [
            '_controller' => function ($request, $parameters) {
                return (new DatabasesController())->show($request, $parameters['serverId'], $parameters['databaseId']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['serverId' => '[a-zA-Z0-9\-]+', 'databaseId' => '\d+'], // requirements
        [], // options
        '', // host
        [], // schemes
        ['GET']
    ));

    // Create server database (POST)
    $routes->add('pterodactylpanelapi-databases-store', new Route(
        '/api/application/servers/{serverId}/databases',
        [
            '_controller' => function ($request, $parameters) {
                return (new DatabasesController())->store($request, $parameters['serverId']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['serverId' => '[a-zA-Z0-9\-]+'], // requirements
        [], // options
        '', // host
        [], // schemes
        ['POST']
    ));

    // Update server database (PATCH)
    $routes->add('pterodactylpanelapi-databases-update', new Route(
        '/api/application/servers/{serverId}/databases/{databaseId}',
        [
            '_controller' => function ($request, $parameters) {
                return (new DatabasesController())->update($request, $parameters['serverId'], $parameters['databaseId']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['serverId' => '[a-zA-Z0-9\-]+', 'databaseId' => '\d+'], // requirements
        [], // options
        '', // host
        [], // schemes
        ['PATCH']
    ));

    // Reset database password (POST)
    $routes->add('pterodactylpanelapi-databases-reset-password', new Route(
        '/api/application/servers/{serverId}/databases/{databaseId}/reset-password',
        [
            '_controller' => function ($request, $parameters) {
                return (new DatabasesController())->resetPassword($request, $parameters['serverId'], $parameters['databaseId']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['serverId' => '[a-zA-Z0-9\-]+', 'databaseId' => '\d+'], // requirements
        [], // options
        '', // host
        [], // schemes
        ['POST']
    ));

    // Delete server database (DELETE)
    $routes->add('pterodactylpanelapi-databases-destroy', new Route(
        '/api/application/servers/{serverId}/databases/{databaseId}',
        [
            '_controller' => function ($request, $parameters) {
                return (new DatabasesController())->destroy($request, $parameters['serverId'], $parameters['databaseId']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['serverId' => '[a-zA-Z0-9\-]+', 'databaseId' => '\d+'], // requirements
        [], // options
        '', // host
        [], // schemes
        ['DELETE']
    ));

};
