<?php

declare(strict_types=1);

namespace Angelma\Repos;

final class Posts extends Repo
{
    protected const TABLE = 'posts';
    public const STATUSES = ['draft', 'published'];
    public const COMMENT_MODES = ['closed', 'users', 'anyone'];

    private const SELECT = 'SELECT p.*, u.display_name AS author_name, b.slug AS blog_slug, b.title AS blog_title,
        b.owner_id AS blog_owner_id FROM posts p
        LEFT JOIN users u ON u.id = p.author_id JOIN blogs b ON b.id = p.blog_id';

    /** @return array<string, mixed>|null post with author name and blog fields */
    public function find(int $id): ?array
    {
        return $this->db->one(self::SELECT . ' WHERE p.id = ?', [$id]);
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(int $blogId, string $slug): ?array
    {
        return $this->db->one(self::SELECT . ' WHERE p.blog_id = ? AND p.slug = ?', [$blogId, $slug]);
    }

    /** @return list<array<string, mixed>> every post of a blog, drafts first, then newest */
    public function forBlog(int $blogId): array
    {
        return $this->db->all(
            self::SELECT . " WHERE p.blog_id = ?
            ORDER BY CASE WHEN p.status = 'draft' THEN 0 ELSE 1 END, p.published_at DESC, p.id DESC",
            [$blogId],
        );
    }

    /** @return list<array<string, mixed>> published posts, newest first */
    public function publishedForBlog(int $blogId, int $limit, int $offset = 0): array
    {
        return $this->db->all(
            self::SELECT . " WHERE p.blog_id = ? AND p.status = 'published'
            ORDER BY p.published_at DESC, p.id DESC LIMIT ? OFFSET ?",
            [$blogId, max(0, $limit), max(0, $offset)],
        );
    }

    public function countPublished(int $blogId): int
    {
        return (int) $this->db->value(
            "SELECT COUNT(*) FROM posts WHERE blog_id = ? AND status = 'published'",
            [$blogId],
        );
    }

    /** @return list<array<string, mixed>> newest edited posts */
    public function recent(int $limit): array
    {
        return $this->db->all(self::SELECT . ' ORDER BY p.updated_at DESC, p.id DESC LIMIT ?', [$limit]);
    }

    public function slugTaken(int $blogId, string $slug, int $exceptId = 0): bool
    {
        return $this->db->value(
            'SELECT 1 FROM posts WHERE blog_id = ? AND slug = ? AND id <> ?',
            [$blogId, $slug, $exceptId],
        ) !== null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $data = self::only($data, [
            'blog_id', 'slug', 'title', 'body_markdown', 'status', 'comments_mode', 'author_id', 'published_at',
        ]);
        $data['created_at'] = $data['updated_at'] = now();
        return $this->insertRow($data);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $data = self::only($data, ['slug', 'title', 'body_markdown', 'status', 'comments_mode', 'published_at']);
        $data['updated_at'] = now();
        $this->updateRow($id, $data);
    }

    /** Delete a post and its comments. */
    public function delete(int $id): void
    {
        $this->db->transaction(function () use ($id): void {
            $this->db->execute('DELETE FROM comments WHERE post_id = ?', [$id]);
            $this->db->execute('DELETE FROM posts WHERE id = ?', [$id]);
        });
    }
}
