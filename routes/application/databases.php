<?php

use App\Addons\pterodactylpanelapi\controllers\application\DatabasesController;
use App\Addons\pterodactylpanelapi\middleware\PterodactylKeyAuth;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

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
		],
		['serverId' => '[a-zA-Z0-9\-]+', 'databaseId' => '\d+'], // requirements
		[], // options
		'', // host
		[], // schemes
		['DELETE']
	));

};
