<?php

use Symfony\Component\Routing\Route;
use App\Addons\pterodactylpanelapi\controllers\application\NestsController;
use App\Addons\pterodactylpanelapi\middleware\PterodactylKeyAuth;

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
		],
		['nestId' => '\d+', 'eggId' => '\d+'], // requirements
		[], // options
		'', // host
		[], // schemes
		['GET']
	));

};
