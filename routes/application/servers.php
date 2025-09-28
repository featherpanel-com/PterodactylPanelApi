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
use App\Addons\pterodactylpanelapi\controllers\application\ServersController;
use Symfony\Component\Routing\Route;


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
		],
		['server' => '\d+'], // requirements - server must be numeric
		[], // options
		'', // host
		[], // schemes
		['PATCH']
	));
};
