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
use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Permissions;
use App\Addons\pterodactylpanelapi\controllers\HomeController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

return function (RouteCollection $routes): void {
	$routes->add('pterodactylpanelapi-settings', new Route(
		'/api/pterodactylpanelapi-settings',
		[
			'_controller' => function ($request, $parameters) {
				return (new HomeController())->index($request);
			},
			'_middleware' => [
				PterodactylKeyAuth::class,
			],
			'_permission' => Permissions::ADMIN_PLUGINS_MANAGE,
		],
		[], // requirements
		[], // options
		'', // host
		[], // schemes
		['GET']
	));

};

