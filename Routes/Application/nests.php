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
use App\Addons\pterodactylpanelapi\controllers\application\NestsController;

return function ($routes) {

    // List all nests (GET)
    $routes->add('pterodactylpanelapi-nests-index', new Route(
        '/api/application/nests',
        [
            '_controller' => function ($request, $parameters) {
                return (new NestsController())->index($request);
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

    // Get nest details (GET)
    $routes->add('pterodactylpanelapi-nests-show', new Route(
        '/api/application/nests/{nestId}',
        [
            '_controller' => function ($request, $parameters) {
                return (new NestsController())->show($request, $parameters['nestId']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['nestId' => '\d+'], // requirements
        [], // options
        '', // host
        [], // schemes
        ['GET']
    ));

    // List nest eggs (GET)
    $routes->add('pterodactylpanelapi-nests-eggs', new Route(
        '/api/application/nests/{nestId}/eggs',
        [
            '_controller' => function ($request, $parameters) {
                return (new NestsController())->eggs($request, $parameters['nestId']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['nestId' => '\d+'], // requirements
        [], // options
        '', // host
        [], // schemes
        ['GET']
    ));

    // Get egg details (GET)
    $routes->add('pterodactylpanelapi-nests-egg-details', new Route(
        '/api/application/nests/{nestId}/eggs/{eggId}',
        [
            '_controller' => function ($request, $parameters) {
                return (new NestsController())->eggDetails($request, $parameters['nestId'], $parameters['eggId']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['nestId' => '\d+', 'eggId' => '\d+'], // requirements
        [], // options
        '', // host
        [], // schemes
        ['GET']
    ));

};
