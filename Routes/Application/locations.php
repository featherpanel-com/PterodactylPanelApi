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
