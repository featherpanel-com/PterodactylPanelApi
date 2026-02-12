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

namespace App\Addons\pterodactylpanelapi\controllers\client;

use App\Chat\User;
use App\Helpers\ApiResponse;
use OpenApi\Attributes as OA;
use App\Helpers\PermissionHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Addons\pterodactylpanelapi\middleware\PterodactylKeyAuth;

#[OA\Tag(name: 'Plugin - Pterodactyl API - Client Account', description: 'Client-facing account endpoints for the Pterodactyl compatibility API.')]
class AccountController
{
    #[OA\Get(
        path: '/api/client/account',
        summary: 'Get account details',
        description: 'Returns the authenticated client user details in the Pterodactyl `user` object format.',
        tags: ['Plugin - Pterodactyl API - Client Account'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Account details.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'object', type: 'string', example: 'user'),
                        new OA\Property(
                            property: 'attributes',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'admin', type: 'boolean'),
                                new OA\Property(property: 'username', type: 'string'),
                                new OA\Property(property: 'email', type: 'string', format: 'email'),
                                new OA\Property(property: 'first_name', type: 'string'),
                                new OA\Property(property: 'last_name', type: 'string'),
                                new OA\Property(property: 'language', type: 'string'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden.'),
        ]
    )]
    public function show(Request $request): Response
    {
        $result = $this->resolveClientUser($request);
        if ($result instanceof Response) {
            return $result;
        }

        $user = $result;

        return ApiResponse::sendManualResponse([
            'object' => 'user',
            'attributes' => [
                'id' => (int) $user['id'],
                'admin' => $this->resolveAdminFlag($user),
                'username' => (string) $user['username'],
                'email' => (string) $user['email'],
                'first_name' => (string) $user['first_name'],
                'last_name' => (string) $user['last_name'],
                'language' => 'en',
            ],
        ], 200);
    }

    #[OA\Put(
        path: '/api/client/account/email',
        summary: 'Update account email',
        description: 'Updates the email address for the authenticated client user.',
        tags: ['Plugin - Pterodactyl API - Client Account'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 204, description: 'Email updated successfully.'),
            new OA\Response(response: 400, description: 'Invalid request payload or password.'),
            new OA\Response(response: 409, description: 'Email already in use.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden.'),
            new OA\Response(response: 500, description: 'Internal server error.'),
        ]
    )]
    public function updateEmail(Request $request): Response
    {
        $result = $this->resolveClientUser($request);
        if ($result instanceof Response) {
            return $result;
        }

        /** @var array $user */
        $user = $result;

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'email',
                        'detail' => 'The email must be a valid email address.',
                        'source' => ['field' => 'email'],
                    ],
                ],
            ], 400);
        }

        $email = isset($payload['email']) ? trim((string) $payload['email']) : '';
        $password = isset($payload['password']) ? (string) $payload['password'] : '';

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'email',
                        'detail' => 'The email must be a valid email address.',
                        'source' => ['field' => 'email'],
                    ],
                ],
            ], 400);
        }

        if ($password === '' || !isset($user['password']) || !is_string($user['password']) || !password_verify($password, $user['password'])) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'InvalidPasswordProvidedException',
                        'status' => '400',
                        'detail' => 'The password provided was invalid for this account.',
                    ],
                ],
            ], 400);
        }

        if (strcasecmp($email, (string) $user['email']) !== 0) {
            $existingUser = User::getUserByEmail($email);
            if ($existingUser !== null && (int) $existingUser['id'] !== (int) $user['id']) {
                return ApiResponse::sendManualResponse([
                    'errors' => [
                        [
                            'code' => 'email',
                            'detail' => 'The email address is already in use.',
                            'source' => ['field' => 'email'],
                        ],
                    ],
                ], 409);
            }

            if (!User::updateUser($user['uuid'], ['email' => $email])) {
                return ApiResponse::sendManualResponse([
                    'errors' => [
                        [
                            'code' => 'EmailUpdateFailedException',
                            'status' => '500',
                            'detail' => 'Unable to update the email address.',
                        ],
                    ],
                ], 500);
            }

            $request->attributes->set('user', array_merge($user, ['email' => $email]));
        }

        return new Response('', 204);
    }

    #[OA\Put(
        path: '/api/client/account/password',
        summary: 'Update account password',
        description: 'Updates the password for the authenticated client user.',
        tags: ['Plugin - Pterodactyl API - Client Account'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string', format: 'password'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 204, description: 'Password updated successfully.'),
            new OA\Response(response: 400, description: 'Validation failed or password mismatch.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden.'),
            new OA\Response(response: 422, description: 'Password confirmation does not match.'),
            new OA\Response(response: 500, description: 'Internal server error.'),
        ]
    )]
    public function updatePassword(Request $request): Response
    {
        $result = $this->resolveClientUser($request);
        if ($result instanceof Response) {
            return $result;
        }

        /** @var array $user */
        $user = $result;

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->validationErrorResponse([
                $this->buildValidationError('current_password', 'The current password field is required.'),
                $this->buildValidationError('password', 'The password field is required.'),
                $this->buildValidationError('password_confirmation', 'The password confirmation field is required.'),
            ]);
        }

        $currentPassword = isset($payload['current_password']) ? (string) $payload['current_password'] : '';
        $newPassword = isset($payload['password']) ? (string) $payload['password'] : '';
        $confirmPassword = isset($payload['password_confirmation']) ? (string) $payload['password_confirmation'] : '';

        $errors = [];
        if ($currentPassword === '') {
            $errors[] = $this->buildValidationError('current_password', 'The current password field is required.');
        }
        if ($newPassword === '') {
            $errors[] = $this->buildValidationError('password', 'The password field is required.');
        }
        if ($confirmPassword === '') {
            $errors[] = $this->buildValidationError('password_confirmation', 'The password confirmation field is required.');
        }

        if (!empty($errors)) {
            return $this->validationErrorResponse($errors);
        }

        if ($newPassword !== $confirmPassword) {
            return $this->validationErrorResponse([
                [
                    'code' => 'ValidationException',
                    'status' => '422',
                    'detail' => 'The password confirmation does not match.',
                    'meta' => [
                        'source_field' => 'password_confirmation',
                        'rule' => 'confirmed',
                    ],
                ],
            ]);
        }

        if (strlen($newPassword) < 8) {
            return $this->validationErrorResponse([
                [
                    'code' => 'ValidationException',
                    'status' => '422',
                    'detail' => 'The password must be at least 8 characters.',
                    'meta' => [
                        'source_field' => 'password',
                        'rule' => 'min',
                    ],
                ],
            ]);
        }

        if (!isset($user['password']) || !is_string($user['password']) || !password_verify($currentPassword, $user['password'])) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'InvalidPasswordProvidedException',
                        'status' => '400',
                        'detail' => 'The password provided was invalid for this account.',
                    ],
                ],
            ], 400);
        }

        $update = [
            'password' => password_hash($newPassword, PASSWORD_BCRYPT),
            'remember_token' => User::generateAccountToken(),
        ];

        if (!User::updateUser($user['uuid'], $update)) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    [
                        'code' => 'PasswordUpdateFailedException',
                        'status' => '500',
                        'detail' => 'Unable to update the account password.',
                    ],
                ],
            ], 500);
        }

        return new Response('', 204);
    }

    private function resolveClientUser(Request $request): array | Response
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

        $apiClient = PterodactylKeyAuth::getCurrentApiClient($request);
        if (!is_array($apiClient)) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    'code' => 'AuthenticationException',
                    'status' => 401,
                    'detail' => 'Unauthenticated.',
                ],
            ], 401);
        }

        $user = $request->attributes->get('user');
        if (!is_array($user) || !isset($user['id'])) {
            $ownerId = isset($apiClient['created_by']) ? (int) $apiClient['created_by'] : 0;
            if ($ownerId <= 0) {
                return ApiResponse::sendManualResponse([
                    'errors' => [
                        'code' => 'NotFoundHttpException',
                        'status' => 404,
                        'detail' => 'The requested resource could not be found.',
                    ],
                ], 404);
            }

            $user = User::getUserById($ownerId);
            if ($user === null) {
                return ApiResponse::sendManualResponse([
                    'errors' => [
                        'code' => 'NotFoundHttpException',
                        'status' => 404,
                        'detail' => 'The requested resource could not be found.',
                    ],
                ], 404);
            }

            $request->attributes->set('user', $user);
        }

        if (($user['deleted'] ?? 'false') === 'true') {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    'code' => 'AuthorizationException',
                    'status' => 403,
                    'detail' => 'Forbidden.',
                ],
            ], 403);
        }

        return $user;
    }

    private function resolveAdminFlag(array $user): bool
    {
        if (isset($user['uuid']) && is_string($user['uuid']) && PermissionHelper::hasPermission($user['uuid'], 'admin.root')) {
            return true;
        }

        $roleId = null;
        if (isset($user['role_id']) && is_numeric($user['role_id'])) {
            $roleId = (int) $user['role_id'];
        }

        return $roleId !== null && $roleId >= 4;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildValidationError(string $field, string $detail): array
    {
        return [
            'code' => 'ValidationException',
            'status' => '422',
            'detail' => $detail,
            'meta' => [
                'source_field' => $field,
                'rule' => 'required',
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $errors
     */
    private function validationErrorResponse(array $errors): Response
    {
        return ApiResponse::sendManualResponse([
            'errors' => $errors,
        ], 422);
    }
}
