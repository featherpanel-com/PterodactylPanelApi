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

namespace App\Addons\pterodactylpanelapi\middleware;

use App\Chat\User;
use App\Helpers\ApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Addons\pterodactylpanelapi\chat\PterodactylApiChat;

class PterodactylKeyAuth
{
    public function handle(Request $request, callable $next): Response
    {
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    'code' => 'AuthenticationException',
                    'status' => 401,
                    'detail' => 'Unauthenticated.',
                ],
            ], 401);
        }

        $providedKey = $matches[1];
        $record = PterodactylApiChat::getByKey($providedKey);
        if ($record === null || ($record['deleted'] ?? 'false') === 'true') {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    'code' => 'AuthenticationException',
                    'status' => 401,
                    'detail' => 'Unauthenticated.',
                ],
            ], 401);
        }

        $requiredType = $request->attributes->get('required_pterodactyl_key_type');
        if ($requiredType === null) {
            $path = $request->getPathInfo();
            if (preg_match('#^/api/application(?:/|$)#', $path) === 1) {
                $requiredType = 'admin';
            } elseif (preg_match('#^/api/client(?:/|$)#', $path) === 1) {
                $requiredType = 'client';
            }
        }
        if ($requiredType !== null && $record['type'] !== $requiredType) {
            return ApiResponse::sendManualResponse([
                'errors' => [
                    'code' => 'AuthorizationException',
                    'status' => 403,
                    'detail' => 'Forbidden.',
                ],
            ], 403);
        }

        PterodactylApiChat::update((int) $record['id'], ['last_used' => date('Y-m-d H:i:s')]);

        $request->attributes->set('pterodactyl_key', $record);
        $request->attributes->set('pterodactyl_api_key_type', $record['type']);
        $request->attributes->set('api_client', $record);
        $request->attributes->set('api_key_type', $record['type']);
        $request->attributes->set('auth_type', 'api_key');

        return $next($request);
    }

    /**
     * Get the authenticated user from the request (if available).
     */
    public static function getCurrentUser(Request $request): ?array
    {
        return $request->attributes->get('user');
    }

    /**
     * Get the API client from the request (if authenticated via API key).
     */
    public static function getCurrentApiClient(Request $request): ?array
    {
        return $request->attributes->get('api_client');
    }

    /**
     * Get the authentication type from the request.
     */
    public static function getAuthType(Request $request): ?string
    {
        return $request->attributes->get('auth_type');
    }

    /**
     * Check if the request is authenticated via API key.
     */
    public static function isApiKeyAuth(Request $request): bool
    {
        return $request->attributes->get('auth_type') === 'api_key';
    }

    /**
     * Check if the request is authenticated via session.
     */
    public static function isSessionAuth(Request $request): bool
    {
        return $request->attributes->get('auth_type') === 'session';
    }
}
