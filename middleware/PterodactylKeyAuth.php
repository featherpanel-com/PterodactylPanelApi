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
