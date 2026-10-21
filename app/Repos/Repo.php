<?php

declare(strict_types=1);

namespace Angelma\Repos;

use Angelma\App;
use Angelma\Db;

/**
 * Base for the repositories. Each repository owns the SQL for one table.
 */
abstract class Repo
{
    protected const TABLE = '';

    public function __construct(protected ?Db $db = null)
    {
        $this->db ??= App::db();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->one('SELECT * FROM ' . static::TABLE . ' WHERE id = ?', [$id]);
    }

    public function count(): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM ' . static::TABLE);
    }

    /** @param array<string, mixed> $data */
    protected function insertRow(array $data): int
    {
        return $this->db->insert(static::TABLE, $data);
    }

    /** @param array<string, mixed> $data */
    protected function updateRow(int $id, array $data): void
    {
        $this->db->update(static::TABLE, $id, $data);
    }

    /**
     * Keep only allowed keys.
     *
     * @param array<string, mixed> $data
     * @param list<string> $allowed
     * @return array<string, mixed>
     */
    protected static function only(array $data, array $allowed): array
    {
        return array_intersect_key($data, array_flip($allowed));
    }
}
