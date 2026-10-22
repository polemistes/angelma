<?php

declare(strict_types=1);

namespace Angelma\Repos;

final class Blogs extends Repo
{
    protected const TABLE = 'blogs';

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        return $this->db->one('SELECT * FROM blogs WHERE slug = ?', [$slug]);
    }

    /** @return list<array<string, mixed>> blogs with the creator's name and post count */
    public function all(): array
    {
        return $this->db->all(
            'SELECT b.*, u.display_name AS owner_name,
                (SELECT COUNT(*) FROM posts p WHERE p.blog_id = b.id) AS post_count
                FROM blogs b LEFT JOIN users u ON u.id = b.owner_id ORDER BY b.title, b.id',
        );
    }

    public function slugTaken(string $slug, int $exceptId = 0): bool
    {
        return $this->db->value('SELECT 1 FROM blogs WHERE slug = ? AND id <> ?', [$slug, $exceptId]) !== null;
    }

    /** @param array<string, mixed> $data slug, title, description, owner_id */
    public function create(array $data): int
    {
        $data = self::only($data, ['slug', 'title', 'description', 'owner_id']);
        $data['description'] ??= '';
        $data['created_at'] = now();
        return $this->insertRow($data);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $this->updateRow($id, self::only($data, ['slug', 'title', 'description']));
    }

    public function postCount(int $id): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM posts WHERE blog_id = ?', [$id]);
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM blogs WHERE id = ?', [$id]);
    }
}
