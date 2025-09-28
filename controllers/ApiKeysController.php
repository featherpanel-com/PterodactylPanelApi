<?php

namespace App\Addons\pterodactylpanelapi\controllers;

use App\Addons\pterodactylpanelapi\chat\PterodactylApiChat;
use App\Helpers\ApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeysController
{
	public function index(Request $request): Response
	{
		$page = (int) $request->query->get('page', 1);
		$limit = (int) $request->query->get('limit', 10);
		$search = $request->query->get('search', '');

		$offset = ($page - 1) * $limit;
		$keys = PterodactylApiChat::getAll($search, $limit, $offset);
		$total = PterodactylApiChat::getCount($search);

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
		], 'API keys fetched successfully', 200);
	}

	public function show(Request $request, int $id): Response
	{
		$key = PterodactylApiChat::getById($id);
		if (!$key) {
			return ApiResponse::error('API key not found', 'API_KEY_NOT_FOUND', 404);
		}

		return ApiResponse::success(['key' => $key], 'API key fetched successfully', 200);
	}

	public function create(Request $request): Response
	{
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

		$user = $request->get('user');
		$createdBy = is_array($user) && isset($user['id']) ? (int) $user['id'] : ($data['created_by'] ?? null);
		if ($createdBy === null) {
			return ApiResponse::error('created_by is required', 'MISSING_REQUIRED_FIELDS', 400);
		}

		$insertData = [
			'name' => $data['name'],
			'key' => $data['key'],
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
		$existing = PterodactylApiChat::getById($id);
		if (!$existing) {
			return ApiResponse::error('API key not found', 'API_KEY_NOT_FOUND', 404);
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
		$existing = PterodactylApiChat::getById($id);
		if (!$existing) {
			return ApiResponse::error('API key not found', 'API_KEY_NOT_FOUND', 404);
		}
		$ok = PterodactylApiChat::delete($id);
		if (!$ok) {
			return ApiResponse::error('Failed to delete API key', 'API_KEY_DELETE_FAILED', 400);
		}
		return ApiResponse::success([], 'API key deleted successfully', 200);
	}
}


