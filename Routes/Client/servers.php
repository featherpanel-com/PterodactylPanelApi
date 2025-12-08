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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;
use App\Addons\pterodactylpanelapi\middleware\PterodactylKeyAuth;
use App\Addons\pterodactylpanelapi\controllers\client\FilesController;
use App\Addons\pterodactylpanelapi\controllers\client\BackupsController;
use App\Addons\pterodactylpanelapi\controllers\client\ServersController;
use App\Addons\pterodactylpanelapi\controllers\client\SubusersController;
use App\Addons\pterodactylpanelapi\controllers\client\DatabasesController;
use App\Addons\pterodactylpanelapi\controllers\client\SchedulesController;
use App\Addons\pterodactylpanelapi\controllers\client\AllocationsController;

return function (RouteCollection $routes): void {
    $routes->add('pterodactylpanelapi-client-servers', new Route(
        '/api/client',
        [
            '_controller' => function (Request $request) {
                return (new ServersController())->index($request);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        [], // requirements
        [], // options
        '', // host
        [], // schemes
        ['GET']
    ));

    $routes->add('pterodactylpanelapi-client-server-show', new Route(
        '/api/client/servers/{identifier}',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new ServersController())->show($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['GET']
    ));

    $routes->add('pterodactylpanelapi-client-server-websocket', new Route(
        '/api/client/servers/{identifier}/websocket',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new ServersController())->websocket($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['GET']
    ));

    $routes->add('pterodactylpanelapi-client-server-resources', new Route(
        '/api/client/servers/{identifier}/resources',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new ServersController())->resources($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['GET']
    ));

    $routes->add('pterodactylpanelapi-client-server-command', new Route(
        '/api/client/servers/{identifier}/command',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new ServersController())->command($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['POST']
    ));

    $routes->add('pterodactylpanelapi-client-server-power', new Route(
        '/api/client/servers/{identifier}/power',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new ServersController())->power($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['POST']
    ));

    $routes->add('pterodactylpanelapi-client-server-rename', new Route(
        '/api/client/servers/{identifier}/settings/rename',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new ServersController())->rename($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['POST']
    ));

    $routes->add('pterodactylpanelapi-client-server-reinstall', new Route(
        '/api/client/servers/{identifier}/settings/reinstall',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new ServersController())->reinstall($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['POST']
    ));

    $routes->add('pterodactylpanelapi-client-server-startup', new Route(
        '/api/client/servers/{identifier}/startup',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new ServersController())->startup($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['GET']
    ));

    $routes->add('pterodactylpanelapi-client-server-startup-variable', new Route(
        '/api/client/servers/{identifier}/startup/variable',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new ServersController())->updateStartupVariable($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['PUT']
    ));

    $routes->add('pterodactylpanelapi-client-server-backups-index', new Route(
        '/api/client/servers/{identifier}/backups',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new BackupsController())->list($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['GET']
    ));

    $routes->add('pterodactylpanelapi-client-server-backups-store', new Route(
        '/api/client/servers/{identifier}/backups',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new BackupsController())->create($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['POST']
    ));

    $routes->add('pterodactylpanelapi-client-server-backups-show', new Route(
        '/api/client/servers/{identifier}/backups/{backup}',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new BackupsController())->view($request, $parameters['identifier'], $parameters['backup']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        [
            'identifier' => '[a-zA-Z0-9\-]+',
            'backup' => '[a-fA-F0-9\-]+',
        ],
        [],
        '',
        [],
        ['GET']
    ));

    $routes->add('pterodactylpanelapi-client-server-backups-download', new Route(
        '/api/client/servers/{identifier}/backups/{backup}/download',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new BackupsController())->download($request, $parameters['identifier'], $parameters['backup']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        [
            'identifier' => '[a-zA-Z0-9\-]+',
            'backup' => '[a-fA-F0-9\-]+',
        ],
        [],
        '',
        [],
        ['GET']
    ));

    $routes->add('pterodactylpanelapi-client-server-backups-destroy', new Route(
        '/api/client/servers/{identifier}/backups/{backup}',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new BackupsController())->destroy($request, $parameters['identifier'], $parameters['backup']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        [
            'identifier' => '[a-zA-Z0-9\-]+',
            'backup' => '[a-fA-F0-9\-]+',
        ],
        [],
        '',
        [],
        ['DELETE']
    ));

    $routes->add('pterodactylpanelapi-client-server-allocations-index', new Route(
        '/api/client/servers/{identifier}/network/allocations',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new AllocationsController())->list($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['GET']
    ));

    $routes->add('pterodactylpanelapi-client-server-allocations-create', new Route(
        '/api/client/servers/{identifier}/network/allocations',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new AllocationsController())->create($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['POST']
    ));

    $routes->add('pterodactylpanelapi-client-server-allocations-notes', new Route(
        '/api/client/servers/{identifier}/network/allocations/{allocation}',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new AllocationsController())->updateNotes($request, $parameters['identifier'], $parameters['allocation']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        [
            'identifier' => '[a-zA-Z0-9\-]+',
            'allocation' => '\d+',
        ],
        [],
        '',
        [],
        ['POST']
    ));

    $routes->add('pterodactylpanelapi-client-server-allocations-primary', new Route(
        '/api/client/servers/{identifier}/network/allocations/{allocation}/primary',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new AllocationsController())->setPrimary($request, $parameters['identifier'], $parameters['allocation']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        [
            'identifier' => '[a-zA-Z0-9\-]+',
            'allocation' => '\d+',
        ],
        [],
        '',
        [],
        ['POST']
    ));

    $routes->add('pterodactylpanelapi-client-server-allocations-destroy', new Route(
        '/api/client/servers/{identifier}/network/allocations/{allocation}',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new AllocationsController())->destroy($request, $parameters['identifier'], $parameters['allocation']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        [
            'identifier' => '[a-zA-Z0-9\-]+',
            'allocation' => '\d+',
        ],
        [],
        '',
        [],
        ['DELETE']
    ));

    $routes->add('pterodactylpanelapi-client-server-users-index', new Route(
        '/api/client/servers/{identifier}/users',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new SubusersController())->list($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['GET']
    ));

    $routes->add('pterodactylpanelapi-client-server-users-create', new Route(
        '/api/client/servers/{identifier}/users',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new SubusersController())->create($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['POST']
    ));

    $routes->add('pterodactylpanelapi-client-server-users-view', new Route(
        '/api/client/servers/{identifier}/users/{subuser}',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new SubusersController())->view($request, $parameters['identifier'], $parameters['subuser']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        [
            'identifier' => '[a-zA-Z0-9\-]+',
            'subuser' => '[a-fA-F0-9\-]+',
        ],
        [],
        '',
        [],
        ['GET']
    ));

    $routes->add('pterodactylpanelapi-client-server-users-update', new Route(
        '/api/client/servers/{identifier}/users/{subuser}',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new SubusersController())->update($request, $parameters['identifier'], $parameters['subuser']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        [
            'identifier' => '[a-zA-Z0-9\-]+',
            'subuser' => '[a-fA-F0-9\-]+',
        ],
        [],
        '',
        [],
        ['POST']
    ));

    $routes->add('pterodactylpanelapi-client-server-users-destroy', new Route(
        '/api/client/servers/{identifier}/users/{subuser}',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new SubusersController())->destroy($request, $parameters['identifier'], $parameters['subuser']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        [
            'identifier' => '[a-zA-Z0-9\-]+',
            'subuser' => '[a-fA-F0-9\-]+',
        ],
        [],
        '',
        [],
        ['DELETE']
    ));

    $routes->add('pterodactylpanelapi-client-server-databases-index', new Route(
        '/api/client/servers/{identifier}/databases',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new DatabasesController())->list($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['GET']
    ));

    $routes->add('pterodactylpanelapi-client-server-databases-create', new Route(
        '/api/client/servers/{identifier}/databases',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new DatabasesController())->store($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['POST']
    ));

    $routes->add('pterodactylpanelapi-client-server-databases-rotate', new Route(
        '/api/client/servers/{identifier}/databases/{database}/rotate-password',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new DatabasesController())->rotatePassword($request, $parameters['identifier'], $parameters['database']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        [
            'identifier' => '[a-zA-Z0-9\-]+',
            'database' => '[A-Za-z0-9]+',
        ],
        [],
        '',
        [],
        ['POST']
    ));

    $routes->add('pterodactylpanelapi-client-server-databases-destroy', new Route(
        '/api/client/servers/{identifier}/databases/{database}',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new DatabasesController())->destroy($request, $parameters['identifier'], $parameters['database']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        [
            'identifier' => '[a-zA-Z0-9\-]+',
            'database' => '[A-Za-z0-9]+',
        ],
        [],
        '',
        [],
        ['DELETE']
    ));

    $routes->add('pterodactylpanelapi-client-server-schedules-index', new Route(
        '/api/client/servers/{identifier}/schedules',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new SchedulesController())->list($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['GET']
    ));

    $routes->add('pterodactylpanelapi-client-server-schedules-create', new Route(
        '/api/client/servers/{identifier}/schedules',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new SchedulesController())->create($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['POST']
    ));

    $routes->add('pterodactylpanelapi-client-server-schedules-view', new Route(
        '/api/client/servers/{identifier}/schedules/{schedule}',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new SchedulesController())->view($request, $parameters['identifier'], $parameters['schedule']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        [
            'identifier' => '[a-zA-Z0-9\-]+',
            'schedule' => '\d+',
        ],
        [],
        '',
        [],
        ['GET']
    ));

    $routes->add('pterodactylpanelapi-client-server-schedules-update', new Route(
        '/api/client/servers/{identifier}/schedules/{schedule}',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new SchedulesController())->update($request, $parameters['identifier'], $parameters['schedule']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        [
            'identifier' => '[a-zA-Z0-9\-]+',
            'schedule' => '\d+',
        ],
        [],
        '',
        [],
        ['POST']
    ));

    $routes->add('pterodactylpanelapi-client-server-schedules-destroy', new Route(
        '/api/client/servers/{identifier}/schedules/{schedule}',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new SchedulesController())->destroy($request, $parameters['identifier'], $parameters['schedule']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        [
            'identifier' => '[a-zA-Z0-9\-]+',
            'schedule' => '\d+',
        ],
        [],
        '',
        [],
        ['DELETE']
    ));

    $routes->add('pterodactylpanelapi-client-server-schedules-tasks-create', new Route(
        '/api/client/servers/{identifier}/schedules/{schedule}/tasks',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new SchedulesController())->createTask($request, $parameters['identifier'], $parameters['schedule']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        [
            'identifier' => '[a-zA-Z0-9\-]+',
            'schedule' => '\d+',
        ],
        [],
        '',
        [],
        ['POST']
    ));

    $routes->add('pterodactylpanelapi-client-server-schedules-tasks-update', new Route(
        '/api/client/servers/{identifier}/schedules/{schedule}/tasks/{task}',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new SchedulesController())->updateTask($request, $parameters['identifier'], $parameters['schedule'], $parameters['task']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        [
            'identifier' => '[a-zA-Z0-9\-]+',
            'schedule' => '\d+',
            'task' => '\d+',
        ],
        [],
        '',
        [],
        ['POST']
    ));

    $routes->add('pterodactylpanelapi-client-server-schedules-tasks-destroy', new Route(
        '/api/client/servers/{identifier}/schedules/{schedule}/tasks/{task}',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new SchedulesController())->destroyTask($request, $parameters['identifier'], $parameters['schedule'], $parameters['task']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        [
            'identifier' => '[a-zA-Z0-9\-]+',
            'schedule' => '\d+',
            'task' => '\d+',
        ],
        [],
        '',
        [],
        ['DELETE']
    ));

    $routes->add('pterodactylpanelapi-client-server-files-list', new Route(
        '/api/client/servers/{identifier}/files/list',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new FilesController())->list($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['GET']
    ));

    $routes->add('pterodactylpanelapi-client-server-files-contents', new Route(
        '/api/client/servers/{identifier}/files/contents',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new FilesController())->contents($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['GET']
    ));

    $routes->add('pterodactylpanelapi-client-server-files-download', new Route(
        '/api/client/servers/{identifier}/files/download',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new FilesController())->download($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['GET']
    ));

    $routes->add('pterodactylpanelapi-client-server-files-rename', new Route(
        '/api/client/servers/{identifier}/files/rename',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new FilesController())->rename($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['PUT']
    ));

    $routes->add('pterodactylpanelapi-client-server-files-copy', new Route(
        '/api/client/servers/{identifier}/files/copy',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new FilesController())->copy($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['POST']
    ));

    $routes->add('pterodactylpanelapi-client-server-files-write', new Route(
        '/api/client/servers/{identifier}/files/write',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new FilesController())->write($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['POST']
    ));

    $routes->add('pterodactylpanelapi-client-server-files-compress', new Route(
        '/api/client/servers/{identifier}/files/compress',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new FilesController())->compress($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['POST']
    ));

    $routes->add('pterodactylpanelapi-client-server-files-decompress', new Route(
        '/api/client/servers/{identifier}/files/decompress',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new FilesController())->decompress($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['POST']
    ));

    $routes->add('pterodactylpanelapi-client-server-files-delete', new Route(
        '/api/client/servers/{identifier}/files/delete',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new FilesController())->delete($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['POST']
    ));

    $routes->add('pterodactylpanelapi-client-server-files-create-folder', new Route(
        '/api/client/servers/{identifier}/files/create-folder',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new FilesController())->createFolder($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['POST']
    ));

    $routes->add('pterodactylpanelapi-client-server-files-upload', new Route(
        '/api/client/servers/{identifier}/files/upload',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new FilesController())->upload($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['GET']
    ));

    $routes->add('pterodactylpanelapi-client-server-activity-index', new Route(
        '/api/client/servers/{identifier}/activity',
        [
            '_controller' => function (Request $request, array $parameters) {
                return (new ServersController())->activity($request, $parameters['identifier']);
            },
            '_middleware' => [
                PterodactylKeyAuth::class,
            ],
            'required_pterodactyl_key_type' => 'client',
        ],
        ['identifier' => '[a-zA-Z0-9\-]+'],
        [],
        '',
        [],
        ['GET']
    ));
};
