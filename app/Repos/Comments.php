<?php

declare(strict_types=1);

namespace Angelma\Repos;

final class Comments extends Repo
{
    protected const TABLE = 'comments';

    private const SELECT = 'SELECT c.*, p.title AS post_title, p.slug AS post_slug, p.author_id AS post_author_id,
        b.slug AS blog_slug, b.owner_id AS blog_owner_id, u.display_name AS user_display_name
        FROM comments c JOIN posts p ON p.id = c.post_id JOIN blogs b ON b.id = p.blog_id
        LEFT JOIN users u ON u.id = c.user_id';

    /** @return array<string, mixed>|null comment with its post and blog fields */
    public function find(int $id): ?array
    {
        return $this->db->one(self::SELECT . ' WHERE c.id = ?', [$id]);
    }

    /**
     * Comments of a post in time order. Pending ones are included only when asked.
     *
     * @return list<array<string, mixed>>
     */
    public function forPost(int $postId, bool $includePending = false): array
    {
        $sql = self::SELECT . ' WHERE c.post_id = ?';
        if (!$includePending) {
            $sql .= " AND c.status = 'approved'";
        }
        return $this->db->all($sql . ' ORDER BY c.created_at, c.id', [$postId]);
    }

    /**
     * Pending comments, oldest first, optionally only on posts in one owner's blogs.
     *
     * @return list<array<string, mixed>>
     */
    public function pendingFor(?int $blogOwnerId = null): array
    {
        return $this->listByStatus('pending', $blogOwnerId, 'ASC', 500);
    }

    /** @return list<array<string, mixed>> newest approved comments */
    public function recentApproved(?int $blogOwnerId = null, int $limit = 50): array
    {
        return $this->listByStatus('approved', $blogOwnerId, 'DESC', $limit);
    }

    public function countPending(?int $blogOwnerId = null): int
    {
        $sql = "SELECT COUNT(*) FROM comments c JOIN posts p ON p.id = c.post_id JOIN blogs b ON b.id = p.blog_id
            WHERE c.status = 'pending'";
        $params = [];
        if ($blogOwnerId !== null) {
            $sql .= ' AND b.owner_id = ?';
            $params[] = $blogOwnerId;
        }
        return (int) $this->db->value($sql, $params);
    }

    /** Number of comments from an IP hash since a UTC timestamp. */
    public function countSince(string $ipHash, string $since): int
    {
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM comments WHERE ip_hash = ? AND created_at >= ?',
            [$ipHash, $since],
        );
    }

    /** @param array<string, mixed> $data post_id, user_id, author_name, body, status, ip_hash */
    public function create(array $data): int
    {
        $data = self::only($data, ['post_id', 'user_id', 'author_name', 'body', 'status', 'ip_hash']);
        $data['created_at'] = now();
        return $this->insertRow($data);
    }

    public function approve(int $id): void
    {
        $this->updateRow($id, ['status' => 'approved']);
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM comments WHERE id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> */
    private function listByStatus(string $status, ?int $blogOwnerId, string $order, int $limit): array
    {
        $sql = self::SELECT . ' WHERE c.status = ?';
        $params = [$status];
        if ($blogOwnerId !== null) {
            $sql .= ' AND b.owner_id = ?';
            $params[] = $blogOwnerId;
        }
        $order = $order === 'ASC' ? 'ASC' : 'DESC';
        $params[] = $limit;
        return $this->db->all($sql . " ORDER BY c.created_at $order, c.id $order LIMIT ?", $params);
    }
}
