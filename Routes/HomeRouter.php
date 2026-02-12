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

use App\Permissions;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use App\Addons\pterodactylpanelapi\controllers\HomeController;
use App\Addons\pterodactylpanelapi\middleware\PterodactylKeyAuth;

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
            'required_pterodactyl_key_type' => 'admin',
            '_permission' => Permissions::ADMIN_PLUGINS_MANAGE,
        ],
        [], // requirements
        [], // options
        '', // host
        [], // schemes
        ['GET']
    ));

};
