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

namespace App\Addons\pterodactylpanelapi\utils;

use App\Helpers\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared helpers for shaping Pterodactyl-compatible HTTP responses from the translation layer.
 */
final class PterodactylResponse
{
    public const ACCEPT_HEADER = 'application/vnd.pterodactyl.v1+json';

    public static function error(string $code, int $status, string $detail, ?array $meta = null): Response
    {
        $error = [
            'code' => $code,
            'status' => (string) $status,
            'detail' => $detail,
        ];

        if ($meta !== null) {
            $error['meta'] = $meta;
        }

        return ApiResponse::sendManualResponse(['errors' => [$error]], $status);
    }

    public static function list(array $data, array $meta = []): Response
    {
        $payload = [
            'object' => 'list',
            'data' => $data,
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return ApiResponse::sendManualResponse($payload, 200);
    }

    public static function item(array $attributes, string $object = 'object'): Response
    {
        return ApiResponse::sendManualResponse([
            'object' => $object,
            'attributes' => $attributes,
        ], 200);
    }

    public static function wantsPterodactylJson(?string $acceptHeader): bool
    {
        if ($acceptHeader === null || trim($acceptHeader) === '') {
            return true;
        }

        return str_contains(strtolower($acceptHeader), 'application/vnd.pterodactyl.v1+json');
    }
}