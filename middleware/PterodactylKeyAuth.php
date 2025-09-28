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

namespace App\Addons\pterodactylpanelapi\middleware;

use App\Chat\User;
use App\Helpers\ApiResponse;
use App\Addons\pterodactylpanelapi\chat\PterodactylApiChat;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PterodactylKeyAuth
{
	public function handle(Request $request, callable $next): Response
	{

		// Check for Authorization header (Bearer token for plugin API keys)
		$authHeader = $request->headers->get('Authorization');
		if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
			$providedKey = $matches[1];
			$record = PterodactylApiChat::getByKey($providedKey);
			if ($record == null) {
				return ApiResponse::sendManualResponse([
					'errors' => [
						'code' => 'AuthenticationException',
						'status' => 401,
						'detail' => 'Unauthenticated.'
					]
				], 401);
			}
			// Optionally update last_used
			PterodactylApiChat::update((int) $record['id'], ['last_used' => date('Y-m-d H:i:s')]);

			// Attach info (no user binding in this addon context)
			$request->attributes->set('pterodactyl_key', $record);
			$request->attributes->set('auth_type', 'api_key');
		} else {
			return ApiResponse::sendManualResponse([
				'errors' => [
					'code' => 'AuthenticationException',
					'status' => 401,
					'detail' => 'Unauthenticated.'
				]
			], 401);
		}


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
