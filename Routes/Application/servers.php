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
use App\Addons\pterodactylpanelapi\middleware\PterodactylKeyAuth;
use App\Addons\pterodactylpanelapi\controllers\application\ServersController;

return function ($routes) {
    // List all servers (GET)
    $routes->add('pterodactylpanelapi-servers-index', new Route(
        '/api/application/servers',
        [
            '_controller' => function ($request, $parameters) {
                return (new ServersController())->index($request);
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

    // Create new server (POST)
    $routes->add('pterodactylpanelapi-servers-store', new Route(
        '/api/application/servers',
        [
            '_controller' => function ($request, $parameters) {
                return (new ServersController())->store($request);
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

    // Get server details by ID (GET)
    $routes->add('pterodactylpanelapi-servers-show', new Route(
        '/api/application/servers/{server}',
        [
            '_controller' => function ($request, $parameters) {
                return (new ServersController())->show($request, $parameters['server']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['server' => '\d+'], // requirements - server must be numeric
        [], // options
        '', // host
        [], // schemes
        ['GET']
    ));

    // Get server details by external ID (GET)
    $routes->add('pterodactylpanelapi-servers-show-external', new Route(
        '/api/application/servers/external/{external_id}',
        [
            '_controller' => function ($request, $parameters) {
                return (new ServersController())->showExternal($request, $parameters['external_id']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['external_id' => '[^/]+'], // requirements - external_id can be any string except /
        [], // options
        '', // host
        [], // schemes
        ['GET']
    ));

    // Suspend server (POST)
    $routes->add('pterodactylpanelapi-servers-suspend', new Route(
        '/api/application/servers/{server}/suspend',
        [
            '_controller' => function ($request, $parameters) {
                return (new ServersController())->suspend($request, $parameters['server']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['server' => '\d+'], // requirements - server must be numeric
        [], // options
        '', // host
        [], // schemes
        ['POST']
    ));

    // Unsuspend server (POST)
    $routes->add('pterodactylpanelapi-servers-unsuspend', new Route(
        '/api/application/servers/{server}/unsuspend',
        [
            '_controller' => function ($request, $parameters) {
                return (new ServersController())->unsuspend($request, $parameters['server']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['server' => '\d+'], // requirements - server must be numeric
        [], // options
        '', // host
        [], // schemes
        ['POST']
    ));

    // Reinstall server (POST)
    $routes->add('pterodactylpanelapi-servers-reinstall', new Route(
        '/api/application/servers/{server}/reinstall',
        [
            '_controller' => function ($request, $parameters) {
                return (new ServersController())->reinstall($request, $parameters['server']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['server' => '\d+'], // requirements - server must be numeric
        [], // options
        '', // host
        [], // schemes
        ['POST']
    ));

    // Delete server (DELETE)
    $routes->add('pterodactylpanelapi-servers-delete', new Route(
        '/api/application/servers/{server}',
        [
            '_controller' => function ($request, $parameters) {
                return (new ServersController())->destroy($request, $parameters['server']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['server' => '\d+'], // requirements - server must be numeric
        [], // options
        '', // host
        [], // schemes
        ['DELETE']
    ));

    // Update server details (PATCH)
    $routes->add('pterodactylpanelapi-servers-update-details', new Route(
        '/api/application/servers/{server}/details',
        [
            '_controller' => function ($request, $parameters) {
                return (new ServersController())->updateDetails($request, $parameters['server']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['server' => '\d+'], // requirements - server must be numeric
        [], // options
        '', // host
        [], // schemes
        ['PATCH']
    ));

    // Update server build configuration (PATCH)
    $routes->add('pterodactylpanelapi-servers-update-build', new Route(
        '/api/application/servers/{server}/build',
        [
            '_controller' => function ($request, $parameters) {
                return (new ServersController())->updateBuild($request, $parameters['server']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['server' => '\d+'], // requirements - server must be numeric
        [], // options
        '', // host
        [], // schemes
        ['PATCH']
    ));

    // Update server startup (PATCH)
    $routes->add('pterodactylpanelapi-servers-update-startup', new Route(
        '/api/application/servers/{server}/startup',
        [
            '_controller' => function ($request, $parameters) {
                return (new ServersController())->updateStartup($request, $parameters['server']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['server' => '\d+'], // requirements - server must be numeric
        [], // options
        '', // host
        [], // schemes
        ['PATCH']
    ));
};
