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

namespace App\Addons\pterodactylpanelapi\controllers;

use App\Helpers\ApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Addons\pterodactylpanelapi\chat\PterodactylApiChat;

class ApiKeysController
{
    public function index(Request $request): Response
    {
        try {
            $context = $this->resolveContext($request);
        } catch (\RuntimeException $e) {
            return ApiResponse::error('Authentication required', 'AUTH_REQUIRED', 401);
        }

        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 10);
        $search = $request->query->get('search', '');

        $offset = ($page - 1) * $limit;
        $keys = PterodactylApiChat::getAll($search, $limit, $offset, $context['type'], $context['owner_id']);
        $total = PterodactylApiChat::getCount($search, $context['type'], $context['owner_id']);

        $totalPages = (int) ceil($total / max(1, $limit));
        $from = $total === 0 ? 0 : (($page - 1) * $limit + 1);
        $to = $total === 0 ? 0 : min($from + $limit - 1, $total);

        return ApiResponse::success([
            'keys' => $keys,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total_records' => $total,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1,
                'from' => $from,
                'to' => $to,
            ],
            'search' => [
                'query' => $search,
                'has_results' => count($keys) > 0,
            ],
            'context' => [
                'type' => $context['type'],
                'scope' => $context['scope'],
            ],
        ], 'API keys fetched successfully', 200);
    }

    public function show(Request $request, int $id): Response
    {
        try {
            $context = $this->resolveContext($request);
        } catch (\RuntimeException $e) {
            return ApiResponse::error('Authentication required', 'AUTH_REQUIRED', 401);
        }

        $key = PterodactylApiChat::getById($id);
        $response = $this->ensureAccessible($key, $context);
        if ($response !== null) {
            return $response;
        }

        return ApiResponse::success(['key' => $key], 'API key fetched successfully', 200);
    }

    public function create(Request $request): Response
    {
        try {
            $context = $this->resolveContext($request);
        } catch (\RuntimeException $e) {
            return ApiResponse::error('Authentication required', 'AUTH_REQUIRED', 401);
        }

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ApiResponse::error('Invalid JSON in request body', 'INVALID_JSON', 400);
        }

        $required = ['name', 'key'];
        $missing = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                $missing[] = $field;
            }
        }
        if (!empty($missing)) {
            return ApiResponse::error('Missing required fields: ' . implode(', ', $missing), 'MISSING_REQUIRED_FIELDS', 400);
        }
        if (!is_string($data['name'])) {
            return ApiResponse::error('Name must be a string', 'INVALID_DATA_TYPE', 400);
        }
        if (!is_string($data['key'])) {
            return ApiResponse::error('Key must be a string', 'INVALID_DATA_TYPE', 400);
        }
        if (isset($data['last_used']) && !empty($data['last_used'])) {
            $ts = strtotime($data['last_used']);
            if ($ts === false) {
                return ApiResponse::error('Invalid datetime for last_used', 'INVALID_DATA_TYPE', 400);
            }
            $data['last_used'] = date('Y-m-d H:i:s', $ts);
        }

        $actor = $context['user'];
        $actorId = is_array($actor) && isset($actor['id']) ? (int) $actor['id'] : null;

        if ($context['owner_id'] !== null) {
            $createdBy = $context['owner_id'];
        } else {
            $createdBy = isset($data['created_by']) ? (int) $data['created_by'] : $actorId;
        }

        if ($createdBy === null) {
            return ApiResponse::error('created_by is required', 'MISSING_REQUIRED_FIELDS', 400);
        }
        $createdBy = (int) $createdBy;
        if ($createdBy <= 0) {
            return ApiResponse::error('created_by must be a positive integer', 'INVALID_DATA_TYPE', 400);
        }

        $insertData = [
            'name' => $data['name'],
            'key' => $data['key'],
            'type' => $context['type'],
            'last_used' => $data['last_used'] ?? null,
            'created_by' => $createdBy,
        ];

        $id = PterodactylApiChat::create($insertData);
        if (!$id) {
            return ApiResponse::error('Failed to create API key', 'API_KEY_CREATE_FAILED', 400);
        }

        $key = PterodactylApiChat::getById($id);

        return ApiResponse::success(['key' => $key], 'API key created successfully', 201);
    }

    public function update(Request $request, int $id): Response
    {
        try {
            $context = $this->resolveContext($request);
        } catch (\RuntimeException $e) {
            return ApiResponse::error('Authentication required', 'AUTH_REQUIRED', 401);
        }

        $existing = PterodactylApiChat::getById($id);
        $response = $this->ensureAccessible($existing, $context);
        if ($response !== null) {
            return $response;
        }

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ApiResponse::error('Invalid JSON in request body', 'INVALID_JSON', 400);
        }
        if (empty($data)) {
            return ApiResponse::error('No data provided', 'NO_DATA_PROVIDED', 400);
        }
        $update = [];
        if (isset($data['name'])) {
            if (!is_string($data['name'])) {
                return ApiResponse::error('Name must be a string', 'INVALID_DATA_TYPE', 400);
            }
            $update['name'] = $data['name'];
        }
        if (isset($data['key'])) {
            if (!is_string($data['key'])) {
                return ApiResponse::error('Key must be a string', 'INVALID_DATA_TYPE', 400);
            }
            $update['key'] = $data['key'];
        }
        if (isset($data['last_used'])) {
            if (!empty($data['last_used'])) {
                $ts = strtotime($data['last_used']);
                if ($ts === false) {
                    return ApiResponse::error('Invalid datetime for last_used', 'INVALID_DATA_TYPE', 400);
                }
                $update['last_used'] = date('Y-m-d H:i:s', $ts);
            } else {
                $update['last_used'] = null;
            }
        }
        $ok = PterodactylApiChat::update($id, $update);
        if (!$ok) {
            return ApiResponse::error('Failed to update API key', 'API_KEY_UPDATE_FAILED', 400);
        }
        $key = PterodactylApiChat::getById($id);

        return ApiResponse::success(['key' => $key], 'API key updated successfully', 200);
    }

    public function delete(Request $request, int $id): Response
    {
        try {
            $context = $this->resolveContext($request);
        } catch (\RuntimeException $e) {
            return ApiResponse::error('Authentication required', 'AUTH_REQUIRED', 401);
        }

        $existing = PterodactylApiChat::getById($id);
        $response = $this->ensureAccessible($existing, $context);
        if ($response !== null) {
            return $response;
        }
        $ok = PterodactylApiChat::delete($id);
        if (!$ok) {
            return ApiResponse::error('Failed to delete API key', 'API_KEY_DELETE_FAILED', 400);
        }

        return ApiResponse::success([], 'API key deleted successfully', 200);
    }

    /**
     * Resolve the context (admin/global or client/self) for the incoming request.
     *
     * @throws \RuntimeException when authentication context is missing for client scope
     */
    private function resolveContext(Request $request): array
    {
        $type = $request->attributes->get('api_key_type');
        if ($type === null) {
            $type = $request->attributes->get('pterodactyl_api_key_type', 'admin');
        }
        if (!in_array($type, ['admin', 'client'], true)) {
            $type = 'admin';
        }

        $scope = $request->attributes->get('pterodactyl_api_key_scope');
        if ($scope === null) {
            $scope = $type === 'client' ? 'self' : 'global';
        }

        $user = $request->attributes->get('user');
        $ownerId = null;

        if ($scope === 'self') {
            if (!is_array($user) || !isset($user['id'])) {
                throw new \RuntimeException('AUTH_REQUIRED');
            }
            $ownerId = (int) $user['id'];
        }

        return [
            'type' => $type,
            'scope' => $scope,
            'owner_id' => $ownerId,
            'user' => is_array($user) ? $user : null,
        ];
    }

    private function forbidResponse(): Response
    {
        return ApiResponse::error('API key inaccessible in this context', 'API_KEY_FORBIDDEN', 403);
    }

    private function ensureAccessible(?array $key, array $context): ?Response
    {
        if ($key === null) {
            return ApiResponse::error('API key not found', 'API_KEY_NOT_FOUND', 404);
        }

        if (($key['deleted'] ?? 'false') === 'true') {
            return ApiResponse::error('API key not found', 'API_KEY_NOT_FOUND', 404);
        }

        if (($key['type'] ?? null) !== $context['type']) {
            return $this->forbidResponse();
        }

        if ($context['owner_id'] !== null && (int) $key['created_by'] !== $context['owner_id']) {
            return $this->forbidResponse();
        }

        return null;
    }
}
