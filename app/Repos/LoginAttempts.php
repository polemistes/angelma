<?php

declare(strict_types=1);

namespace Angelma\Repos;

final class LoginAttempts extends Repo
{
    protected const TABLE = 'login_attempts';

    public function record(string $key, string $at): void
    {
        $this->insertRow(['key' => $key, 'attempted_at' => $at]);
    }

    public function countSince(string $key, string $since): int
    {
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM login_attempts WHERE key = ? AND attempted_at >= ?',
            [$key, $since],
        );
    }

    /** Newest attempt for a key, or null. */
    public function latest(string $key): ?string
    {
        $value = $this->db->value('SELECT MAX(attempted_at) FROM login_attempts WHERE key = ?', [$key]);
        return $value === null ? null : (string) $value;
    }

    public function clear(string $key): void
    {
        $this->db->execute('DELETE FROM login_attempts WHERE key = ?', [$key]);
    }

    public function deleteOlderThan(string $before): void
    {
        $this->db->execute('DELETE FROM login_attempts WHERE attempted_at < ?', [$before]);
    }
}
