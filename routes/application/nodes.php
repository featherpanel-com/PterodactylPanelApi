<?php

/*
 * This file is part of FeatherPanel.
 * Please view the LICENSE file that was distributed with this source code.
 *
 * # MythicalSystems License v2.0
 *
 * ## Copyright (c) 2021–2025 MythicalSystems and Cassian Gherman
 *
 * Breaking any of the following rules will result in a permanent ban from the MythicalSystems community and all of its services.
 */

use App\Addons\pterodactylpanelapi\middleware\PterodactylKeyAuth;
use App\Addons\pterodactylpanelapi\controllers\application\NodesController;
use Symfony\Component\Routing\Route;


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
		],
		['nodeId' => '\d+'], // requirements
		[], // options
		'', // host
		[], // schemes
		['DELETE']
	));

};
