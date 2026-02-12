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
use App\Addons\pterodactylpanelapi\controllers\application\NodesController;

return function ($routes) {
    // List all nodes (GET)
    $routes->add('pterodactylpanelapi-nodes-index', new Route(
        '/api/application/nodes',
        [
            '_controller' => function ($request, $parameters) {
                return (new NodesController())->index($request);
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

    // Get node details (GET)
    $routes->add('pterodactylpanelapi-nodes-show', new Route(
        '/api/application/nodes/{nodeId}',
        [
            '_controller' => function ($request, $parameters) {
                return (new NodesController())->show($request, $parameters['nodeId']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['nodeId' => '\d+'], // requirements
        [], // options
        '', // host
        [], // schemes
        ['GET']
    ));

    // Get deployable nodes (GET)
    $routes->add('pterodactylpanelapi-nodes-deployable', new Route(
        '/api/application/nodes/deployable',
        [
            '_controller' => function ($request, $parameters) {
                return (new NodesController())->deployable($request);
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

    // Create new node (POST)
    $routes->add('pterodactylpanelapi-nodes-store', new Route(
        '/api/application/nodes',
        [
            '_controller' => function ($request, $parameters) {
                return (new NodesController())->store($request);
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

    // Update node configuration (PATCH)
    $routes->add('pterodactylpanelapi-nodes-update', new Route(
        '/api/application/nodes/{nodeId}',
        [
            '_controller' => function ($request, $parameters) {
                return (new NodesController())->update($request, $parameters['nodeId']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['nodeId' => '\d+'], // requirements
        [], // options
        '', // host
        [], // schemes
        ['PATCH']
    ));

    // Get node configuration (GET)
    $routes->add('pterodactylpanelapi-nodes-configuration', new Route(
        '/api/application/nodes/{nodeId}/configuration',
        [
            '_controller' => function ($request, $parameters) {
                return (new NodesController())->configuration($request, $parameters['nodeId']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['nodeId' => '\d+'], // requirements
        [], // options
        '', // host
        [], // schemes
        ['GET']
    ));

    // List node allocations (GET)
    $routes->add('pterodactylpanelapi-nodes-allocations', new Route(
        '/api/application/nodes/{nodeId}/allocations',
        [
            '_controller' => function ($request, $parameters) {
                return (new NodesController())->allocations($request, $parameters['nodeId']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['nodeId' => '\d+'], // requirements
        [], // options
        '', // host
        [], // schemes
        ['GET']
    ));

    // Create node allocations (POST)
    $routes->add('pterodactylpanelapi-nodes-create-allocations', new Route(
        '/api/application/nodes/{nodeId}/allocations',
        [
            '_controller' => function ($request, $parameters) {
                return (new NodesController())->createAllocations($request, $parameters['nodeId']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['nodeId' => '\d+'], // requirements
        [], // options
        '', // host
        [], // schemes
        ['POST']
    ));

    // Delete node allocation (DELETE)
    $routes->add('pterodactylpanelapi-nodes-delete-allocation', new Route(
        '/api/application/nodes/{nodeId}/allocations/{allocationId}',
        [
            '_controller' => function ($request, $parameters) {
                return (new NodesController())->deleteAllocation($request, $parameters['nodeId'], $parameters['allocationId']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['nodeId' => '\d+', 'allocationId' => '\d+'], // requirements
        [], // options
        '', // host
        [], // schemes
        ['DELETE']
    ));

    // Delete node (DELETE)
    $routes->add('pterodactylpanelapi-nodes-delete', new Route(
        '/api/application/nodes/{nodeId}',
        [
            '_controller' => function ($request, $parameters) {
                return (new NodesController())->destroy($request, $parameters['nodeId']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'admin',
        ],
        ['nodeId' => '\d+'], // requirements
        [], // options
        '', // host
        [], // schemes
        ['DELETE']
    ));

};
