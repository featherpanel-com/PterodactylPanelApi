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
