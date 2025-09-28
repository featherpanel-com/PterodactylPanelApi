<?php

namespace App\Addons\pterodactylpanelapi\chat;

use App\Chat\Database;

class PterodactylApiChat
{
	/**
	 * The table name.
	 *
	 * @var string
	 */
	private static string $table = "`featherpanel_pterodactylpanelapi_pterodactyl_api_key`";

	/**
	 * Get all API keys.
	 *
	 * @param string|null $search The search query.
	 * @param int $limit The limit.
	 * @param int $offset The offset.
	 *
	 * @return array The API keys.
	 */
	public static function getAll(?string $search = null, int $limit = 10, int $offset = 0): array
	{
		$pdo = Database::getPdoConnection();
		$sql = 'SELECT * FROM ' . self::$table;
		$params = [];

		if ($search !== null) {
			$sql .= ' WHERE name LIKE :search';
			$params['search'] = '%' . $search . '%';
		}

		$sql .= ' LIMIT :limit OFFSET :offset';
		$stmt = $pdo->prepare($sql);
		if (!empty($params)) {
			foreach ($params as $key => $value) {
				$stmt->bindValue($key, $value);
			}
		}
		$stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
		$stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	 * Get an API key by ID.
	 *
	 * @param int $id The ID.
	 *
	 * @return array|null The API key.
	 */
	public static function getById(int $id): ?array
	{
		$pdo = Database::getPdoConnection();
		$stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE id = :id LIMIT 1');
		$stmt->execute(['id' => $id]);

		return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
	}

	/**
	 * Get an API key by key.
	 *
	 * @param string $key The key.
	 *
	 * @return array|null The API key.
	 */
	public static function getByKey(string $key): ?array
	{
		$pdo = Database::getPdoConnection();
		$stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE `key` = :key LIMIT 1');
		$stmt->execute(['key' => $key]);

		return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
	}

	/**
	 * Get the count of API keys.
	 *
	 * @param string|null $search The search query.
	 *
	 * @return int The count.
	 */
	public static function getCount(?string $search = null): int
	{
		$pdo = Database::getPdoConnection();
		$sql = 'SELECT COUNT(*) FROM ' . self::$table;
		$params = [];

		if ($search !== null) {
			$sql .= ' WHERE name LIKE :search';
			$params['search'] = '%' . $search . '%';
		}

		$stmt = $pdo->prepare($sql);
		if (!empty($params)) {
			$stmt->execute($params);
		} else {
			$stmt->execute();
		}

		return (int) $stmt->fetchColumn();
	}

	/**
	 * Create an API key.
	 *
	 * @param array $data The data.
	 *
	 * @return int|false The ID of the API key.
	 */
	public static function create(array $data): int|false
	{
		$fields = ['name', 'key', 'last_used', 'created_by'];
		$insert = [];
		foreach ($fields as $field) {
			$insert[$field] = $data[$field] ?? null;
		}
		$pdo = Database::getPdoConnection();
		$sql = 'INSERT INTO ' . self::$table . ' (name, `key`, last_used, created_by) VALUES (:name, :key, :last_used, :created_by)';
		$stmt = $pdo->prepare($sql);
		if ($stmt->execute($insert)) {
			return (int) $pdo->lastInsertId();
		}

		return false;
	}

	/**
	 * Update an API key.
	 *
	 * @param int $id The ID.
	 * @param array $data The data.
	 *
	 * @return bool The result.
	 */
	public static function update(int $id, array $data): bool
	{
		$fields = ['name', 'key', 'last_used'];
		$set = [];
		$params = ['id' => $id];
		foreach ($fields as $field) {
			if (isset($data[$field])) {
				$set[] = "`$field` = :$field";
				$params[$field] = $data[$field];
			}
		}
		if (empty($set)) {
			return false;
		}
		$sql = 'UPDATE ' . self::$table . ' SET ' . implode(', ', $set) . ' WHERE id = :id';
		$pdo = Database::getPdoConnection();
		$stmt = $pdo->prepare($sql);

		return $stmt->execute($params);
	}

	/**
	 * Delete an API key.
	 *
	 * @param int $id The ID.
	 *
	 * @return bool The result.
	 */
	public static function delete(int $id): bool
	{
		$pdo = Database::getPdoConnection();
		$stmt = $pdo->prepare('DELETE FROM ' . self::$table . ' WHERE id = :id');

		return $stmt->execute(['id' => $id]);
	}
}