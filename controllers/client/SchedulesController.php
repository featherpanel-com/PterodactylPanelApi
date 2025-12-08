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

use App\App;
use App\Chat\Task;
use App\SubuserPermissions;
use App\Chat\ServerSchedule;
use App\Helpers\ApiResponse;
use OpenApi\Attributes as OA;
use App\Config\ConfigInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[OA\Tag(name: 'Plugin - Pterodactyl API - Client Schedules', description: 'Client-facing schedule and task endpoints for the Pterodactyl compatibility API.')]
class SchedulesController extends ServersController
{
    #[OA\Get(
        path: '/api/client/servers/{identifier}/schedules',
        summary: 'List schedules',
        description: 'Returns all schedules configured for the specified server.',
        tags: ['Plugin - Pterodactyl API - Client Schedules'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Schedule list.', content: new OA\JsonContent(type: 'object')),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
        ]
    )]
    public function list(Request $request, string $identifier): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::SCHEDULE_READ);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $schedules = ServerSchedule::getSchedulesByServerId((int) $context['server']['id']);
        $data = array_map(
            fn (array $schedule): array => $this->formatSchedule($schedule, true),
            $schedules
        );

        return ApiResponse::sendManualResponse([
            'object' => 'list',
            'data' => $data,
        ], 200);
    }

    #[OA\Post(
        path: '/api/client/servers/{identifier}/schedules',
        summary: 'Create schedule',
        description: 'Creates a new schedule for the specified server.',
        tags: ['Plugin - Pterodactyl API - Client Schedules'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'minute', 'hour', 'day_of_month', 'day_of_week'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'minute', type: 'string'),
                    new OA\Property(property: 'hour', type: 'string'),
                    new OA\Property(property: 'day_of_month', type: 'string'),
                    new OA\Property(property: 'day_of_week', type: 'string'),
                    new OA\Property(property: 'month', type: 'string', nullable: true),
                    new OA\Property(property: 'is_active', type: 'boolean', nullable: true),
                    new OA\Property(property: 'only_when_online', type: 'boolean', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Schedule created.', content: new OA\JsonContent(type: 'object')),
            new OA\Response(response: 400, description: 'Schedules disabled or invalid cron expression.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 422, description: 'Validation error.'),
            new OA\Response(response: 500, description: 'Failed to create schedule.'),
        ]
    )]
    public function create(Request $request, string $identifier): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::SCHEDULE_CREATE);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $config = App::getInstance(true)->getConfig();
        if ($config->getSetting(ConfigInterface::SERVER_ALLOW_SCHEDULES, 'true') === 'false') {
            return $this->displayError('Schedules are disabled on this host. Please contact your administrator to enable this feature.', 403);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->validationError('payload', 'The request body must be valid JSON.', 'json');
        }

        $required = ['name', 'minute', 'hour', 'day_of_month', 'day_of_week'];
        foreach ($required as $field) {
            if (!isset($payload[$field]) || !is_string($payload[$field]) || trim($payload[$field]) === '') {
                return $this->validationError($field, 'The ' . $field . ' field is required.', 'required');
            }
        }

        $cronDayOfWeek = trim($payload['day_of_week']);
        $cronMonth = isset($payload['month']) && is_string($payload['month']) ? trim($payload['month']) : '*';
        $cronDayOfMonth = trim($payload['day_of_month']);
        $cronHour = trim($payload['hour']);
        $cronMinute = trim($payload['minute']);

        if (
            !ServerSchedule::validateCronExpression(
                $cronDayOfWeek,
                $cronMonth,
                $cronDayOfMonth,
                $cronHour,
                $cronMinute
            )
        ) {
            return $this->displayError('The supplied cron expression is invalid.', 400);
        }

        $nextRunAt = ServerSchedule::calculateNextRunTime(
            $cronDayOfWeek,
            $cronMonth,
            $cronDayOfMonth,
            $cronHour,
            $cronMinute
        );

        $scheduleId = ServerSchedule::createSchedule([
            'server_id' => (int) $context['server']['id'],
            'name' => trim($payload['name']),
            'cron_day_of_week' => $cronDayOfWeek,
            'cron_month' => $cronMonth,
            'cron_day_of_month' => $cronDayOfMonth,
            'cron_hour' => $cronHour,
            'cron_minute' => $cronMinute,
            'is_active' => $this->boolValue($payload['is_active'] ?? true) ? 1 : 0,
            'is_processing' => 0,
            'only_when_online' => $this->boolValue($payload['only_when_online'] ?? false) ? 1 : 0,
            'next_run_at' => $nextRunAt,
        ]);

        if ($scheduleId === false) {
            return $this->daemonErrorResponse(500, 'Failed to create schedule.');
        }

        $schedule = ServerSchedule::getScheduleById($scheduleId);
        if ($schedule === null) {
            return $this->daemonErrorResponse(500, 'Failed to load the created schedule.');
        }

        $this->logServerActivity(
            $request,
            $context,
            'server:schedule.create',
            [
                'schedule_id' => (int) $schedule['id'],
                'name' => (string) ($schedule['name'] ?? ''),
            ]
        );

        return ApiResponse::sendManualResponse($this->formatSchedule($schedule), 200);
    }

    #[OA\Get(
        path: '/api/client/servers/{identifier}/schedules/{schedule}',
        summary: 'View schedule',
        description: 'Retrieves details for a specific schedule, including tasks.',
        tags: ['Plugin - Pterodactyl API - Client Schedules'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'schedule',
                in: 'path',
                required: true,
                description: 'Schedule ID.',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Schedule details.', content: new OA\JsonContent(type: 'object')),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 404, description: 'Schedule not found.'),
        ]
    )]
    public function view(Request $request, string $identifier, string $scheduleId): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::SCHEDULE_READ);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $schedule = $this->resolveSchedule($context['server'], $scheduleId);
        if ($schedule instanceof Response) {
            return $schedule;
        }

        return ApiResponse::sendManualResponse($this->formatSchedule($schedule, true), 200);
    }

    #[OA\Post(
        path: '/api/client/servers/{identifier}/schedules/{schedule}',
        summary: 'Update schedule',
        description: 'Updates the specified schedule.',
        tags: ['Plugin - Pterodactyl API - Client Schedules'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'schedule',
                in: 'path',
                required: true,
                description: 'Schedule ID.',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(type: 'object')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Schedule updated.', content: new OA\JsonContent(type: 'object')),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 404, description: 'Schedule not found.'),
            new OA\Response(response: 422, description: 'Validation error.'),
            new OA\Response(response: 500, description: 'Failed to update schedule.'),
        ]
    )]
    public function update(Request $request, string $identifier, string $scheduleId): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::SCHEDULE_UPDATE);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $schedule = $this->resolveSchedule($context['server'], $scheduleId);
        if ($schedule instanceof Response) {
            return $schedule;
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->validationError('payload', 'The request body must be valid JSON.', 'json');
        }

        $updates = [];

        if (isset($payload['name'])) {
            if (!is_string($payload['name']) || trim($payload['name']) === '') {
                return $this->validationError('name', 'The name field must be a non-empty string.', 'string');
            }
            $updates['name'] = trim($payload['name']);
        }

        $cronDayOfWeek = $schedule['cron_day_of_week'];
        $cronMonth = $schedule['cron_month'];
        $cronDayOfMonth = $schedule['cron_day_of_month'];
        $cronHour = $schedule['cron_hour'];
        $cronMinute = $schedule['cron_minute'];
        $cronChanged = false;

        if (isset($payload['day_of_week'])) {
            if (!is_string($payload['day_of_week']) || trim($payload['day_of_week']) === '') {
                return $this->validationError('day_of_week', 'The day_of_week field must be a non-empty string.', 'string');
            }
            $cronDayOfWeek = trim($payload['day_of_week']);
            $cronChanged = true;
        }

        if (isset($payload['day_of_month'])) {
            if (!is_string($payload['day_of_month']) || trim($payload['day_of_month']) === '') {
                return $this->validationError('day_of_month', 'The day_of_month field must be a non-empty string.', 'string');
            }
            $cronDayOfMonth = trim($payload['day_of_month']);
            $cronChanged = true;
        }

        if (isset($payload['hour'])) {
            if (!is_string($payload['hour']) || trim($payload['hour']) === '') {
                return $this->validationError('hour', 'The hour field must be a non-empty string.', 'string');
            }
            $cronHour = trim($payload['hour']);
            $cronChanged = true;
        }

        if (isset($payload['minute'])) {
            if (!is_string($payload['minute']) || trim($payload['minute']) === '') {
                return $this->validationError('minute', 'The minute field must be a non-empty string.', 'string');
            }
            $cronMinute = trim($payload['minute']);
            $cronChanged = true;
        }

        if (isset($payload['month'])) {
            if (!is_string($payload['month']) || trim($payload['month']) === '') {
                return $this->validationError('month', 'The month field must be a non-empty string.', 'string');
            }
            $cronMonth = trim($payload['month']);
            $cronChanged = true;
        }

        if ($cronChanged) {
            if (
                !ServerSchedule::validateCronExpression(
                    $cronDayOfWeek,
                    $cronMonth,
                    $cronDayOfMonth,
                    $cronHour,
                    $cronMinute
                )
            ) {
                return $this->displayError('The supplied cron expression is invalid.', 400);
            }

            $updates['cron_day_of_week'] = $cronDayOfWeek;
            $updates['cron_month'] = $cronMonth;
            $updates['cron_day_of_month'] = $cronDayOfMonth;
            $updates['cron_hour'] = $cronHour;
            $updates['cron_minute'] = $cronMinute;
            $updates['next_run_at'] = ServerSchedule::calculateNextRunTime(
                $cronDayOfWeek,
                $cronMonth,
                $cronDayOfMonth,
                $cronHour,
                $cronMinute
            );
        }

        if (isset($payload['is_active'])) {
            $updates['is_active'] = $this->boolValue($payload['is_active']) ? 1 : 0;
        }

        if (isset($payload['only_when_online'])) {
            $updates['only_when_online'] = $this->boolValue($payload['only_when_online']) ? 1 : 0;
        }

        if (empty($updates)) {
            return ApiResponse::sendManualResponse($this->formatSchedule($schedule, true), 200);
        }

        $updates['updated_at'] = date('Y-m-d H:i:s');

        if (!ServerSchedule::updateSchedule((int) $schedule['id'], $updates)) {
            return $this->daemonErrorResponse(500, 'Failed to update the schedule.');
        }

        $updated = ServerSchedule::getScheduleById((int) $schedule['id']);
        if ($updated === null) {
            return $this->daemonErrorResponse(500, 'Failed to load the updated schedule.');
        }

        $this->logServerActivity(
            $request,
            $context,
            'server:schedule.update',
            [
                'schedule_id' => (int) $updated['id'],
            ]
        );

        return ApiResponse::sendManualResponse($this->formatSchedule($updated, true), 200);
    }

    #[OA\Delete(
        path: '/api/client/servers/{identifier}/schedules/{schedule}',
        summary: 'Delete schedule',
        description: 'Deletes the specified schedule and its tasks.',
        tags: ['Plugin - Pterodactyl API - Client Schedules'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'schedule',
                in: 'path',
                required: true,
                description: 'Schedule ID.',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Schedule deleted.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 404, description: 'Schedule not found.'),
            new OA\Response(response: 500, description: 'Failed to delete schedule.'),
        ]
    )]
    public function destroy(Request $request, string $identifier, string $scheduleId): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::SCHEDULE_DELETE);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $schedule = $this->resolveSchedule($context['server'], $scheduleId);
        if ($schedule instanceof Response) {
            return $schedule;
        }

        $tasks = Task::getTasksByScheduleId((int) $schedule['id']);
        foreach ($tasks as $task) {
            Task::deleteTask((int) $task['id']);
        }

        if (!ServerSchedule::deleteSchedule((int) $schedule['id'])) {
            return $this->daemonErrorResponse(500, 'Failed to delete the schedule.');
        }

        $this->logServerActivity(
            $request,
            $context,
            'server:schedule.delete',
            [
                'schedule_id' => (int) $schedule['id'],
            ]
        );

        return new Response('', 204);
    }

    #[OA\Post(
        path: '/api/client/servers/{identifier}/schedules/{schedule}/tasks',
        summary: 'Create schedule task',
        description: 'Adds a task to the specified schedule.',
        tags: ['Plugin - Pterodactyl API - Client Schedules'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'schedule',
                in: 'path',
                required: true,
                description: 'Schedule ID.',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['action', 'payload', 'time_offset'],
                properties: [
                    new OA\Property(property: 'action', type: 'string'),
                    new OA\Property(property: 'payload', type: 'string', nullable: true),
                    new OA\Property(property: 'time_offset', type: 'integer', minimum: 0),
                    new OA\Property(property: 'continue_on_failure', type: 'boolean', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Task created.', content: new OA\JsonContent(type: 'object')),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 404, description: 'Schedule not found.'),
            new OA\Response(response: 422, description: 'Validation error.'),
            new OA\Response(response: 500, description: 'Failed to create task.'),
        ]
    )]
    public function createTask(Request $request, string $identifier, string $scheduleId): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::SCHEDULE_UPDATE);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $schedule = $this->resolveSchedule($context['server'], $scheduleId);
        if ($schedule instanceof Response) {
            return $schedule;
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->validationError('payload', 'The request body must be valid JSON.', 'json');
        }

        if (!isset($payload['action']) || !is_string($payload['action']) || trim($payload['action']) === '') {
            return $this->validationError('action', 'The action field is required.', 'required');
        }

        $action = trim($payload['action']);
        if (!Task::validateAction($action)) {
            return $this->displayError('The provided action is invalid.', 400);
        }

        $rawPayload = $payload['payload'] ?? '';
        $taskPayload = is_string($rawPayload) ? trim($rawPayload) : '';

        if (in_array($action, ['power', 'command'], true) && $taskPayload === '') {
            return $this->validationError('payload', 'The payload field is required for this action.', 'required');
        }

        $timeOffset = $payload['time_offset'] ?? 0;
        if (is_string($timeOffset) && ctype_digit($timeOffset)) {
            $timeOffset = (int) $timeOffset;
        }
        if (!is_int($timeOffset) && !(is_numeric($timeOffset) && (int) $timeOffset == $timeOffset)) {
            return $this->validationError('time_offset', 'The time_offset field must be an integer.', 'integer');
        }
        $timeOffset = (int) $timeOffset;
        if ($timeOffset < 0) {
            return $this->validationError('time_offset', 'The time_offset field must be zero or greater.', 'min:0');
        }

        $taskId = Task::createTask([
            'schedule_id' => (int) $schedule['id'],
            'sequence_id' => Task::getNextSequenceId((int) $schedule['id']),
            'action' => $action,
            'payload' => $taskPayload,
            'time_offset' => $timeOffset,
            'is_queued' => 0,
            'continue_on_failure' => $this->boolValue($payload['continue_on_failure'] ?? false) ? 1 : 0,
        ]);

        if ($taskId === false) {
            return $this->daemonErrorResponse(500, 'Failed to create schedule task.');
        }

        $task = Task::getTaskById($taskId);
        if ($task === null) {
            return $this->daemonErrorResponse(500, 'Failed to load the created task.');
        }

        $this->logServerActivity(
            $request,
            $context,
            'server:schedule-task.create',
            [
                'schedule_id' => (int) $schedule['id'],
                'task_id' => (int) $task['id'],
                'action' => (string) ($task['action'] ?? ''),
            ]
        );

        return ApiResponse::sendManualResponse($this->formatTask($task), 200);
    }

    #[OA\Post(
        path: '/api/client/servers/{identifier}/schedules/{schedule}/tasks/{task}',
        summary: 'Update schedule task',
        description: 'Updates an existing schedule task.',
        tags: ['Plugin - Pterodactyl API - Client Schedules'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'schedule',
                in: 'path',
                required: true,
                description: 'Schedule ID.',
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'task',
                in: 'path',
                required: true,
                description: 'Task ID.',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(type: 'object')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Task updated.', content: new OA\JsonContent(type: 'object')),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 404, description: 'Task not found.'),
            new OA\Response(response: 422, description: 'Validation error.'),
            new OA\Response(response: 500, description: 'Failed to update task.'),
        ]
    )]
    public function updateTask(Request $request, string $identifier, string $scheduleId, string $taskId): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::SCHEDULE_UPDATE);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $schedule = $this->resolveSchedule($context['server'], $scheduleId);
        if ($schedule instanceof Response) {
            return $schedule;
        }

        $task = $this->resolveTask($schedule, $taskId);
        if ($task instanceof Response) {
            return $task;
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->validationError('payload', 'The request body must be valid JSON.', 'json');
        }

        $updates = [];

        if (isset($payload['action'])) {
            if (!is_string($payload['action']) || trim($payload['action']) === '') {
                return $this->validationError('action', 'The action field must be a non-empty string.', 'string');
            }
            $action = trim($payload['action']);
            if (!Task::validateAction($action)) {
                return $this->displayError('The provided action is invalid.', 400);
            }
            $updates['action'] = $action;
        }

        if (isset($payload['payload'])) {
            if (!is_string($payload['payload'])) {
                return $this->validationError('payload', 'The payload field must be a string.', 'string');
            }
            $payloadValue = trim($payload['payload']);
            if (isset($updates['action']) && in_array($updates['action'], ['power', 'command'], true) && $payloadValue === '') {
                return $this->validationError('payload', 'The payload field is required for this action.', 'required');
            }
            if (!isset($updates['action']) && in_array($task['action'], ['power', 'command'], true) && $payloadValue === '') {
                return $this->validationError('payload', 'The payload field is required for this action.', 'required');
            }
            $updates['payload'] = $payloadValue;
        }

        if (isset($payload['time_offset'])) {
            $timeOffset = $payload['time_offset'];
            if (is_string($timeOffset) && ctype_digit($timeOffset)) {
                $timeOffset = (int) $timeOffset;
            }
            if (!is_int($timeOffset) && !(is_numeric($timeOffset) && (int) $timeOffset == $timeOffset)) {
                return $this->validationError('time_offset', 'The time_offset field must be an integer.', 'integer');
            }
            $timeOffset = (int) $timeOffset;
            if ($timeOffset < 0) {
                return $this->validationError('time_offset', 'The time_offset field must be zero or greater.', 'min:0');
            }
            $updates['time_offset'] = $timeOffset;
        }

        if (isset($payload['continue_on_failure'])) {
            $updates['continue_on_failure'] = $this->boolValue($payload['continue_on_failure']) ? 1 : 0;
        }

        if (empty($updates)) {
            return ApiResponse::sendManualResponse($this->formatTask($task), 200);
        }

        $updates['updated_at'] = date('Y-m-d H:i:s');

        if (!Task::updateTask((int) $task['id'], $updates)) {
            return $this->daemonErrorResponse(500, 'Failed to update the schedule task.');
        }

        $updated = Task::getTaskById((int) $task['id']);
        if ($updated === null) {
            return $this->daemonErrorResponse(500, 'Failed to load the updated task.');
        }

        $this->logServerActivity(
            $request,
            $context,
            'server:schedule-task.update',
            [
                'schedule_id' => (int) $schedule['id'],
                'task_id' => (int) $updated['id'],
            ]
        );

        return ApiResponse::sendManualResponse($this->formatTask($updated), 200);
    }

    #[OA\Delete(
        path: '/api/client/servers/{identifier}/schedules/{schedule}/tasks/{task}',
        summary: 'Delete schedule task',
        description: 'Deletes a task from the specified schedule.',
        tags: ['Plugin - Pterodactyl API - Client Schedules'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'schedule',
                in: 'path',
                required: true,
                description: 'Schedule ID.',
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'task',
                in: 'path',
                required: true,
                description: 'Task ID.',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Task deleted.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 404, description: 'Task not found.'),
            new OA\Response(response: 500, description: 'Failed to delete task.'),
        ]
    )]
    public function destroyTask(Request $request, string $identifier, string $scheduleId, string $taskId): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::SCHEDULE_UPDATE);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $schedule = $this->resolveSchedule($context['server'], $scheduleId);
        if ($schedule instanceof Response) {
            return $schedule;
        }

        $task = $this->resolveTask($schedule, $taskId);
        if ($task instanceof Response) {
            return $task;
        }

        if (!Task::deleteTask((int) $task['id'])) {
            return $this->daemonErrorResponse(500, 'Failed to delete the schedule task.');
        }

        Task::reorderTasks((int) $schedule['id']);

        $this->logServerActivity(
            $request,
            $context,
            'server:schedule-task.delete',
            [
                'schedule_id' => (int) $schedule['id'],
                'task_id' => (int) $task['id'],
            ]
        );

        return new Response('', 204);
    }

    private function resolveSchedule(array $server, string $scheduleId): array | Response
    {
        if (!ctype_digit($scheduleId)) {
            return $this->notFoundError('Schedule');
        }

        $schedule = ServerSchedule::getScheduleById((int) $scheduleId);
        if ($schedule === null || (int) $schedule['server_id'] !== (int) $server['id']) {
            return $this->notFoundError('Schedule');
        }

        return $schedule;
    }

    private function resolveTask(array $schedule, string $taskId): array | Response
    {
        if (!ctype_digit($taskId)) {
            return $this->notFoundError('Task');
        }

        $task = Task::getTaskById((int) $taskId);
        if ($task === null || (int) $task['schedule_id'] !== (int) $schedule['id']) {
            return $this->notFoundError('Task');
        }

        return $task;
    }

    private function formatSchedule(array $schedule, bool $includeTasks = false): array
    {
        $attributes = [
            'id' => (int) ($schedule['id'] ?? 0),
            'name' => (string) ($schedule['name'] ?? ''),
            'cron' => [
                'day_of_week' => (string) ($schedule['cron_day_of_week'] ?? '*'),
                'day_of_month' => (string) ($schedule['cron_day_of_month'] ?? '*'),
                'hour' => (string) ($schedule['cron_hour'] ?? '*'),
                'minute' => (string) ($schedule['cron_minute'] ?? '*'),
            ],
            'is_active' => $this->boolValue($schedule['is_active'] ?? false),
            'is_processing' => $this->boolValue($schedule['is_processing'] ?? false),
            'last_run_at' => $this->formatIso8601($schedule['last_run_at'] ?? null),
            'next_run_at' => $this->formatIso8601($schedule['next_run_at'] ?? null),
            'created_at' => $this->formatIso8601($schedule['created_at'] ?? null),
            'updated_at' => $this->formatIso8601($schedule['updated_at'] ?? null),
        ];

        $relationships = [];
        if ($includeTasks) {
            $tasks = Task::getTasksByScheduleId((int) ($schedule['id'] ?? 0));
            $relationships['tasks'] = [
                'object' => 'list',
                'data' => array_map(fn (array $task): array => $this->formatTask($task), $tasks),
            ];
        } else {
            $relationships['tasks'] = [
                'object' => 'list',
                'data' => [],
            ];
        }

        return [
            'object' => 'server_schedule',
            'attributes' => $attributes,
            'relationships' => $relationships,
        ];
    }

    private function formatTask(array $task): array
    {
        return [
            'object' => 'schedule_task',
            'attributes' => [
                'id' => (int) ($task['id'] ?? 0),
                'sequence_id' => (int) ($task['sequence_id'] ?? 0),
                'action' => (string) ($task['action'] ?? ''),
                'payload' => (string) ($task['payload'] ?? ''),
                'time_offset' => (int) ($task['time_offset'] ?? 0),
                'is_queued' => $this->boolValue($task['is_queued'] ?? false),
                'created_at' => $this->formatIso8601($task['created_at'] ?? null),
                'updated_at' => $this->formatIso8601($task['updated_at'] ?? null),
            ],
        ];
    }

    private function validationError(string $field, string $detail, string $rule): Response
    {
        return ApiResponse::sendManualResponse([
            'errors' => [
                [
                    'code' => 'ValidationException',
                    'status' => '422',
                    'detail' => $detail,
                    'meta' => [
                        'source_field' => $field,
                        'rule' => $rule,
                    ],
                ],
            ],
        ], 422);
    }

    private function displayError(string $detail, int $status): Response
    {
        return ApiResponse::sendManualResponse([
            'errors' => [
                [
                    'code' => 'DisplayException',
                    'status' => (string) $status,
                    'detail' => $detail,
                ],
            ],
        ], $status);
    }
}
