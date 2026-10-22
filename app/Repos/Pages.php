<?php

declare(strict_types=1);

namespace Angelma\Repos;

final class Pages extends Repo
{
    protected const TABLE = 'pages';

    /** First path segments a page slug may not use (PLAN.md section 5). */
    public const RESERVED = [
        'admin', 'blog', 'login', 'logout', 'install', 'uploads', 'assets', 'data', 'app', 'vendor', 'dev', 'tests',
    ];

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        return $this->db->one('SELECT * FROM pages WHERE slug = ?', [$slug]);
    }

    /** @return list<array<string, mixed>> all pages in menu order, with the creator's name */
    public function all(): array
    {
        return $this->db->all(
            'SELECT p.*, u.display_name AS owner_name FROM pages p LEFT JOIN users u ON u.id = p.owner_id
             ORDER BY p.menu_order, p.id',
        );
    }

    /** @return list<array<string, mixed>> pages shown in the menu, in order */
    public function menu(): array
    {
        return $this->db->all(
            'SELECT id, slug, name FROM pages WHERE show_in_menu = 1 ORDER BY menu_order, id',
        );
    }

    public function slugTaken(string $slug, int $exceptId = 0): bool
    {
        return $this->db->value('SELECT 1 FROM pages WHERE slug = ? AND id <> ?', [$slug, $exceptId]) !== null;
    }

    /** @param array<string, mixed> $data slug, name, title, meta_description, owner_id */
    public function create(array $data): int
    {
        $data = self::only($data, ['slug', 'name', 'title', 'meta_description', 'owner_id', 'show_in_menu']);
        $data['show_in_menu'] = (int) ($data['show_in_menu'] ?? 1);
        $data['meta_description'] ??= '';
        $data['menu_order'] = (int) $this->db->value('SELECT COALESCE(MAX(menu_order), 0) FROM pages') + 1;
        $data['created_at'] = $data['updated_at'] = now();
        return $this->insertRow($data);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $data = self::only($data, ['slug', 'name', 'title', 'meta_description', 'show_in_menu', 'menu_order']);
        $data['updated_at'] = now();
        $this->updateRow($id, $data);
    }

    public function touch(int $id): void
    {
        $this->updateRow($id, ['updated_at' => now()]);
    }

    /** Delete a page with its rows and boxes. */
    public function delete(int $id): void
    {
        $this->db->transaction(function () use ($id): void {
            $this->db->execute('DELETE FROM boxes WHERE row_id IN (SELECT id FROM rows WHERE page_id = ?)', [$id]);
            $this->db->execute('DELETE FROM rows WHERE page_id = ?', [$id]);
            $this->db->execute('DELETE FROM pages WHERE id = ?', [$id]);
        });
    }

    /** Move a page one step up or down in the menu order. */
    public function moveInMenu(int $id, string $direction): void
    {
        $this->db->transaction(function () use ($id, $direction): void {
            $ids = array_map('intval', array_column($this->all(), 'id'));
            $ids = Rows::reorder($ids, $id, $direction);
            foreach ($ids as $i => $pageId) {
                $this->db->execute('UPDATE pages SET menu_order = ? WHERE id = ?', [$i + 1, $pageId]);
            }
        });
    }

    public function setShowInMenu(int $id, bool $show): void
    {
        $this->db->execute('UPDATE pages SET show_in_menu = ? WHERE id = ?', [(int) $show, $id]);
    }
}
