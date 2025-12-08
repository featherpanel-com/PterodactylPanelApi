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

namespace App\Addons\pterodactylpanelapi\chat;

use App\Chat\Database;

class PterodactylApiChat
{
    /**
     * The table name.
     */
    private static string $table = '`featherpanel_pterodactylpanelapi_pterodactyl_api_key`';

    /**
     * Get all API keys.
     *
     * @param string|null $search the search query
     * @param int $limit the limit
     * @param int $offset the offset
     * @param string|null $type the API key type filter
     * @param int|null $createdBy optional creator filter (used for client keys)
     *
     * @return array the API keys
     */
    public static function getAll(?string $search = null, int $limit = 10, int $offset = 0, ?string $type = null, ?int $createdBy = null): array
    {
        $pdo = Database::getPdoConnection();
        $sql = 'SELECT * FROM ' . self::$table;
        $params = [];
        $conditions = ['`deleted` = \'false\''];

        if ($search !== null) {
            $conditions[] = '`name` LIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        $normalizedType = self::normalizeType($type);
        if ($normalizedType !== null) {
            $conditions[] = '`type` = :type';
            $params['type'] = $normalizedType;
        }

        if ($createdBy !== null) {
            $conditions[] = '`created_by` = :created_by';
            $params['created_by'] = $createdBy;
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' LIMIT :limit OFFSET :offset';
        $stmt = $pdo->prepare($sql);
        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $stmt->bindValue(is_string($key) ? ':' . ltrim($key, ':') : $key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get an API key by ID.
     *
     * @param int $id the ID
     *
     * @return array|null the API key
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
     * @param string $key the key
     *
     * @return array|null the API key
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
     * @param string|null $search the search query
     * @param string|null $type the API key type filter
     * @param int|null $createdBy optional creator filter (used for client keys)
     *
     * @return int the count
     */
    public static function getCount(?string $search = null, ?string $type = null, ?int $createdBy = null): int
    {
        $pdo = Database::getPdoConnection();
        $sql = 'SELECT COUNT(*) FROM ' . self::$table;
        $params = [];
        $conditions = ['`deleted` = \'false\''];

        if ($search !== null) {
            $conditions[] = '`name` LIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        $normalizedType = self::normalizeType($type);
        if ($normalizedType !== null) {
            $conditions[] = '`type` = :type';
            $params['type'] = $normalizedType;
        }

        if ($createdBy !== null) {
            $conditions[] = '`created_by` = :created_by';
            $params['created_by'] = $createdBy;
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $stmt = $pdo->prepare($sql);
        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $stmt->bindValue(is_string($key) ? ':' . ltrim($key, ':') : $key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }
            $stmt->execute();
        } else {
            $stmt->execute();
        }

        return (int) $stmt->fetchColumn();
    }

    /**
     * Create an API key.
     *
     * @param array $data the data
     *
     * @return int|false the ID of the API key
     */
    public static function create(array $data): int | false
    {
        $fields = ['name', 'key', 'type', 'last_used', 'created_by'];
        $insert = [];
        foreach ($fields as $field) {
            $insert[$field] = $data[$field] ?? null;
        }

        $insert['type'] = self::normalizeType($insert['type']) ?? 'admin';

        $pdo = Database::getPdoConnection();
        $sql = 'INSERT INTO ' . self::$table . ' (name, `key`, `type`, last_used, created_by) VALUES (:name, :key, :type, :last_used, :created_by)';
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($insert)) {
            return (int) $pdo->lastInsertId();
        }

        return false;
    }

    /**
     * Update an API key.
     *
     * @param int $id the ID
     * @param array $data the data
     *
     * @return bool the result
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
     * @param int $id the ID
     *
     * @return bool the result
     */
    public static function delete(int $id): bool
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('DELETE FROM ' . self::$table . ' WHERE id = :id');

        return $stmt->execute(['id' => $id]);
    }

    /**
     * Validate the provided API key type.
     */
    private static function normalizeType(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }

        $type = strtolower($type);

        return in_array($type, ['admin', 'client'], true) ? $type : null;
    }
}
