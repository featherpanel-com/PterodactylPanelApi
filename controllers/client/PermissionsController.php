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

namespace App\Addons\pterodactylpanelapi\controllers\client;

use App\Helpers\ApiResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Addons\pterodactylpanelapi\middleware\PterodactylKeyAuth;

#[OA\Tag(name: 'Plugin - Pterodactyl API - Client Permissions', description: 'Client-facing permissions endpoint for the Pterodactyl compatibility API.')]
class PermissionsController
{
    #[OA\Get(
        path: '/api/client/permissions',
        summary: 'List client permissions',
        description: 'Returns the static system permission map exposed by the Pterodactyl client API.',
        tags: ['Plugin - Pterodactyl API - Client Permissions'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Permission map.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'object', type: 'string', example: 'system_permissions'),
                        new OA\Property(
                            property: 'attributes',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'permissions',
                                    type: 'object',
                                    additionalProperties: true
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden.'),
        ]
    )]
    public function index(Request $request): Response
    {
        $keyType = $request->attributes->get('api_key_type');
        if ($keyType === null) {
            $keyType = $request->attributes->get('pterodactyl_api_key_type');
        }

        if ($keyType !== 'client') {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    'code' => 'AuthorizationException',
                    'status' => 403,
                    'detail' => 'Forbidden.',
                ],
            ], 403);
        }

        if (!PterodactylKeyAuth::getCurrentApiClient($request)) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    'code' => 'AuthenticationException',
                    'status' => 401,
                    'detail' => 'Unauthenticated.',
                ],
            ], 401);
        }

        return ApiResponse::sendManualResponse([
            'object' => 'system_permissions',
            'attributes' => [
                'permissions' => [
                    'websocket' => [
                        'description' => 'Allows the user to connect to the server websocket, giving them access to view console output and realtime server stats.',
                        'keys' => [
                            'connect' => 'Allows a user to connect to the websocket instance for a server to stream the console.',
                        ],
                    ],
                    'control' => [
                        'description' => 'Permissions that control a user\'s ability to control the power state of a server, or send commands.',
                        'keys' => [
                            'console' => 'Allows a user to send commands to the server instance via the console.',
                            'start' => 'Allows a user to start the server if it is stopped.',
                            'stop' => 'Allows a user to stop the server if it is running.',
                            'restart' => 'Allows a user to perform a server restart. This allows them to start the server if it is offline, but not put the server in a completely stopped state.',
                        ],
                    ],
                    'user' => [
                        'description' => 'Permissions that allow a user to manage other subusers on a server. They will never be able to edit their own account, or assign permissions they do not have themselves.',
                        'keys' => [
                            'create' => 'Allows a user to create new subusers for the server.',
                            'read' => 'Allows the user to view subusers and their permissions for the server.',
                            'update' => 'Allows a user to modify other subusers.',
                            'delete' => 'Allows a user to delete a subuser from the server.',
                        ],
                    ],
                    'file' => [
                        'description' => 'Permissions that control a user\'s ability to modify the filesystem for this server.',
                        'keys' => [
                            'create' => 'Allows a user to create additional files and folders via the Panel or direct upload.',
                            'read' => 'Allows a user to view the contents of a directory, but not view the contents of or download files.',
                            'read-content' => 'Allows a user to view the contents of a given file. This will also allow the user to download files.',
                            'update' => 'Allows a user to update the contents of an existing file or directory.',
                            'delete' => 'Allows a user to delete files or directories.',
                            'archive' => 'Allows a user to archive the contents of a directory as well as decompress existing archives on the system.',
                            'sftp' => 'Allows a user to connect to SFTP and manage server files using the other assigned file permissions.',
                        ],
                    ],
                    'backup' => [
                        'description' => 'Permissions that control a user\'s ability to generate and manage server backups.',
                        'keys' => [
                            'create' => 'Allows a user to create new backups for this server.',
                            'read' => 'Allows a user to view all backups that exist for this server.',
                            'delete' => 'Allows a user to remove backups from the system.',
                            'download' => 'Allows a user to download a backup for the server. Danger: this allows a user to access all files for the server in the backup.',
                            'restore' => 'Allows a user to restore a backup for the server. Danger: this allows the user to delete all of the server files in the process.',
                        ],
                    ],
                    'allocation' => [
                        'description' => 'Permissions that control a user\'s ability to modify the port allocations for this server.',
                        'keys' => [
                            'read' => 'Allows a user to view all allocations currently assigned to this server. Users with any level of access to this server can always view the primary allocation.',
                            'create' => 'Allows a user to assign additional allocations to the server.',
                            'update' => 'Allows a user to change the primary server allocation and attach notes to each allocation.',
                            'delete' => 'Allows a user to delete an allocation from the server.',
                        ],
                    ],
                    'startup' => [
                        'description' => 'Permissions that control a user\'s ability to view this server\'s startup parameters.',
                        'keys' => [
                            'read' => 'Allows a user to view the startup variables for a server.',
                            'update' => 'Allows a user to modify the startup variables for the server.',
                            'docker-image' => 'Allows a user to modify the Docker image used when running the server.',
                        ],
                    ],
                    'database' => [
                        'description' => 'Permissions that control a user\'s access to the database management for this server.',
                        'keys' => [
                            'create' => 'Allows a user to create a new database for this server.',
                            'read' => 'Allows a user to view the database associated with this server.',
                            'update' => 'Allows a user to rotate the password on a database instance. If the user does not have the view_password permission they will not see the updated password.',
                            'delete' => 'Allows a user to remove a database instance from this server.',
                            'view_password' => 'Allows a user to view the password associated with a database instance for this server.',
                        ],
                    ],
                    'schedule' => [
                        'description' => 'Permissions that control a user\'s access to the schedule management for this server.',
                        'keys' => [
                            'create' => 'Allows a user to create new schedules for this server.',
                            'read' => 'Allows a user to view schedules and the tasks associated with them for this server.',
                            'update' => 'Allows a user to update schedules and schedule tasks for this server.',
                            'delete' => 'Allows a user to delete schedules for this server.',
                        ],
                    ],
                    'settings' => [
                        'description' => 'Permissions that control a user\'s access to the settings for this server.',
                        'keys' => [
                            'rename' => 'Allows a user to rename this server and change the description of it.',
                            'reinstall' => 'Allows a user to trigger a reinstall of this server.',
                        ],
                    ],
                    'activity' => [
                        'description' => 'Permissions that control a user\'s access to the server activity logs.',
                        'keys' => [
                            'read' => 'Allows a user to view the activity logs for the server.',
                        ],
                    ],
                ],
            ],
        ], 200);
    }
}
