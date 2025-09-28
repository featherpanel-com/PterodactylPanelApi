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
use App\Addons\pterodactylpanelapi\controllers\application\LocationsController;
use Symfony\Component\Routing\Route;


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
		],
		['locationId' => '\d+'], // requirements
		[], // options
		'', // host
		[], // schemes
		['DELETE']
	));

};
