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
use App\Addons\pterodactylpanelapi\controllers\application\LocationsController;

return function ($routes) {
    // List all locations (GET)
    $routes->add('pterodactylpanelapi-locations-index', new Route(
        '/api/application/locations',
        [
            '_controller' => function ($request, $parameters) {
                return (new LocationsController())->index($request);
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

    // Get location details (GET)
    $routes->add('pterodactylpanelapi-locations-show', new Route(
        '/api/application/locations/{locationId}',
        [
            '_controller' => function ($request, $parameters) {
                return (new LocationsController())->show($request, $parameters['locationId']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['locationId' => '\d+'], // requirements
        [], // options
        '', // host
        [], // schemes
        ['GET']
    ));

    // Create new location (POST)
    $routes->add('pterodactylpanelapi-locations-store', new Route(
        '/api/application/locations',
        [
            '_controller' => function ($request, $parameters) {
                return (new LocationsController())->store($request);
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

    // Update location (PATCH)
    $routes->add('pterodactylpanelapi-locations-update', new Route(
        '/api/application/locations/{locationId}',
        [
            '_controller' => function ($request, $parameters) {
                return (new LocationsController())->update($request, $parameters['locationId']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['locationId' => '\d+'], // requirements
        [], // options
        '', // host
        [], // schemes
        ['PATCH']
    ));

    // Delete location (DELETE)
    $routes->add('pterodactylpanelapi-locations-destroy', new Route(
        '/api/application/locations/{locationId}',
        [
            '_controller' => function ($request, $parameters) {
                return (new LocationsController())->destroy($request, $parameters['locationId']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['locationId' => '\d+'], // requirements
        [], // options
        '', // host
        [], // schemes
        ['DELETE']
    ));

};
