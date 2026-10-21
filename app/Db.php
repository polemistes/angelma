<?php

declare(strict_types=1);

namespace Angelma;

use PDO;
use PDOStatement;

/**
 * Thin PDO wrapper and migration runner. All driver-specific SQL lives in this class.
 */
final class Db
{
    public function __construct(public readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
    }

    /** Open (or create) a SQLite database file. Use ':memory:' for tests. */
    public static function sqlite(string $path): self
    {
        $db = new self(new PDO('sqlite:' . $path));
        $db->pdo->exec('PRAGMA busy_timeout = 5000');
        $db->pdo->exec('PRAGMA foreign_keys = ON');
        return $db;
    }

    /** @param array<int|string, mixed> $params */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : ':' . ltrim($key, ':');
            $type = match (true) {
                is_int($value), is_bool($value) => PDO::PARAM_INT,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $stmt->bindValue($name, is_bool($value) ? (int) $value : $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function all(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /** @param array<int|string, mixed> $params */
    public function value(string $sql, array $params = []): mixed
    {
        $value = $this->query($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /** @param array<int|string, mixed> $params */
    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * Insert a row and return its id. Column names come from code, never from input.
     *
     * @param array<string, mixed> $data
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $this->assertIdentifiers([$table, ...$columns]);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns)),
        );
        $this->query($sql, $data);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update the row with the given id. Column names come from code, never from input.
     *
     * @param array<string, mixed> $data
     */
    public function update(string $table, int $id, array $data): int
    {
        if ($data === []) {
            return 0;
        }
        $columns = array_keys($data);
        $this->assertIdentifiers([$table, ...$columns]);
        $set = implode(', ', array_map(static fn (string $c): string => $c . ' = :' . $c, $columns));
        $data['__id'] = $id;
        return $this->execute("UPDATE $table SET $set WHERE id = :__id", $data);
    }

    /** Insert or replace a key/value pair (used by the settings table). */
    public function upsertSetting(string $key, string $value): void
    {
        $this->execute(
            'INSERT INTO settings (key, value) VALUES (:k, :v) ON CONFLICT(key) DO UPDATE SET value = excluded.value',
            ['k' => $key, 'v' => $value],
        );
    }

    /**
     * Run a callback inside a transaction. Nested calls join the outer transaction.
     *
     * @template T
     * @param callable(self): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $callback($this);
        }
        $this->pdo->beginTransaction();
        try {
            $result = $callback($this);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Apply every migrations/NNN_*.sql with a number above the stored schema version, in
     * order, each in its own transaction. Returns the number of migrations applied.
     */
    public function migrate(?string $dir = null): int
    {
        $dir ??= dirname(__DIR__) . '/app/migrations';
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS schema_version (version INTEGER NOT NULL)');
        if ($this->value('SELECT COUNT(*) FROM schema_version') == 0) {
            $this->execute('INSERT INTO schema_version (version) VALUES (0)');
        }
        $current = (int) $this->value('SELECT version FROM schema_version');

        $files = glob($dir . '/[0-9][0-9][0-9]_*.sql') ?: [];
        sort($files, SORT_STRING);
        $applied = 0;
        foreach ($files as $file) {
            $number = (int) substr(basename($file), 0, 3);
            if ($number <= $current) {
                continue;
            }
            $sql = (string) file_get_contents($file);
            $this->transaction(function () use ($sql, $number): void {
                $this->pdo->exec($sql);
                $this->execute('UPDATE schema_version SET version = ?', [$number]);
            });
            $current = $number;
            $applied++;
        }
        return $applied;
    }

    /**
     * Consistent copy of the database file, for backups (SQLite VACUUM INTO).
     */
    public function copyTo(string $path): void
    {
        try {
            $this->execute('VACUUM INTO ?', [$path]);
        } catch (\PDOException) {
            // SQLite before 3.27: copy the file while holding a write lock.
            $file = (string) $this->value('SELECT file FROM pragma_database_list WHERE name = ?', ['main']);
            $this->pdo->exec('BEGIN IMMEDIATE');
            try {
                if ($file === '' || !copy($file, $path)) {
                    throw new \RuntimeException('Could not copy the database file.');
                }
            } finally {
                $this->pdo->exec('COMMIT');
            }
        }
    }

    /** @param list<string> $names */
    private function assertIdentifiers(array $names): void
    {
        foreach ($names as $name) {
            if (!preg_match('/^[a-z_][a-z0-9_]*$/', $name)) {
                throw new \InvalidArgumentException('Invalid identifier: ' . $name);
            }
        }
    }
}
