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
