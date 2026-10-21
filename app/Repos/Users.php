<?php

declare(strict_types=1);

namespace Angelma\Repos;

final class Users extends Repo
{
    protected const TABLE = 'users';
    public const ROLES = ['admin', 'editor', 'user'];

    /** @return array<string, mixed>|null */
    public function findByUsername(string $username): ?array
    {
        return $this->db->one('SELECT * FROM users WHERE username = ?', [mb_strtolower(trim($username))]);
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->all('SELECT * FROM users ORDER BY username');
    }

    /** @param array<string, mixed> $data username, display_name, password_hash, role, is_active */
    public function create(array $data): int
    {
        $data = self::only($data, ['username', 'display_name', 'password_hash', 'role', 'is_active']);
        $data['username'] = mb_strtolower(trim((string) $data['username']));
        $data['is_active'] = (int) ($data['is_active'] ?? 1);
        $data['created_at'] = now();
        return $this->insertRow($data);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $this->updateRow($id, self::only($data, ['display_name', 'password_hash', 'role', 'is_active']));
    }

    public function touchLogin(int $id): void
    {
        $this->updateRow($id, ['last_login_at' => now()]);
    }

    public function usernameTaken(string $username): bool
    {
        return $this->findByUsername($username) !== null;
    }

    public function countActiveAdmins(): int
    {
        return (int) $this->db->value("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1");
    }

    /**
     * Delete a user. Their pages, blogs and images move to $heirId; their posts keep
     * existing with $heirId as author; their comments keep the name but lose the link.
     */
    public function delete(int $id, int $heirId): void
    {
        $this->db->transaction(function () use ($id, $heirId): void {
            $this->db->execute('UPDATE pages SET owner_id = ? WHERE owner_id = ?', [$heirId, $id]);
            $this->db->execute('UPDATE blogs SET owner_id = ? WHERE owner_id = ?', [$heirId, $id]);
            $this->db->execute('UPDATE images SET uploader_id = ? WHERE uploader_id = ?', [$heirId, $id]);
            $this->db->execute('UPDATE posts SET author_id = ? WHERE author_id = ?', [$heirId, $id]);
            $this->db->execute('UPDATE comments SET user_id = NULL WHERE user_id = ?', [$id]);
            $this->db->execute('DELETE FROM users WHERE id = ?', [$id]);
        });
    }
}
