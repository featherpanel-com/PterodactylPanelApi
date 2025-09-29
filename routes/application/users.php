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
use App\App;
use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Permissions;
use App\Addons\pterodactylpanelapi\controllers\application\UsersController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

return function (RouteCollection $routes): void {
	// List all users (GET)
	$routes->add('pterodactylpanelapi-users-index', new Route(
		'/api/application/users',
		[
			'_controller' => function ($request, $parameters) {
				return (new UsersController())->index($request);
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

	// Get user by ID (GET)
	$routes->add('pterodactylpanelapi-users-show', new Route(
		'/api/application/users/{user}',
		[
			'_controller' => function ($request, $parameters) {
				return (new UsersController())->show($request, $parameters['user']);
			},
			'_middleware' => [
				PterodactylKeyAuth::class,
			],
		],
		['user' => '\d+'], // requirements - user must be a digit
		[], // options
		'', // host
		[], // schemes
		['GET']
	));

	// Get user by external ID (GET)
	$routes->add('pterodactylpanelapi-users-show-external', new Route(
		'/api/application/users/external/{external_id}',
		[
			'_controller' => function ($request, $parameters) {
				return (new UsersController())->showExternal($request, $parameters['external_id']);
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

	// Create new user (POST)
	$routes->add('pterodactylpanelapi-users-store', new Route(
		'/api/application/users',
		[
			'_controller' => function ($request, $parameters) {
				return (new UsersController())->store($request);
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

	// Update user (PATCH)
	$routes->add('pterodactylpanelapi-users-update', new Route(
		'/api/application/users/{user}',
		[
			'_controller' => function ($request, $parameters) {
				return (new UsersController())->update($request, $parameters['user']);
			},
			'_middleware' => [
				PterodactylKeyAuth::class,
			],
		],
		['user' => '\d+'], // requirements - user must be a digit
		[], // options
		'', // host
		[], // schemes
		['PATCH']
	));

	// Delete user (DELETE)
	$routes->add('pterodactylpanelapi-users-destroy', new Route(
		'/api/application/users/{user}',
		[
			'_controller' => function ($request, $parameters) {
				return (new UsersController())->destroy($request, $parameters['user']);
			},
			'_middleware' => [
				PterodactylKeyAuth::class,
			],
		],
		['user' => '\d+'], // requirements - user must be a digit
		[], // options
		'', // host
		[], // schemes
		['DELETE']
	));
};