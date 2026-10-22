<?php

declare(strict_types=1);

namespace Angelma\Repos;

final class Boxes extends Repo
{
    protected const TABLE = 'boxes';
    public const TYPES = ['text', 'image', 'blog', 'embed', 'container'];
    public const WIDTHS = [3, 4, 6, 8, 9, 12];

    /**
     * A container may be added to a row whose depth (Rows::depth) is below this, so
     * containers nest at most two deep (PLAN.md section 6.13).
     */
    public const MAX_DEPTH = 2;

    /** @return array<string, mixed>|null box with decoded content and its page_id */
    public function find(int $id): ?array
    {
        $box = $this->db->one(
            'SELECT b.*, r.page_id FROM boxes b JOIN rows r ON r.id = b.row_id WHERE b.id = ?',
            [$id],
        );
        return $box === null ? null : self::decode($box);
    }

    /** @return list<array<string, mixed>> */
    public function forRow(int $rowId): array
    {
        return array_map(
            [self::class, 'decode'],
            $this->db->all('SELECT * FROM boxes WHERE row_id = ? ORDER BY position, id', [$rowId]),
        );
    }

    /** @return array<int, list<array<string, mixed>>> boxes of a page grouped by row id */
    public function forPage(int $pageId): array
    {
        $rows = $this->db->all(
            'SELECT b.* FROM boxes b JOIN rows r ON r.id = b.row_id WHERE r.page_id = ? ORDER BY b.position, b.id',
            [$pageId],
        );
        $out = [];
        foreach ($rows as $box) {
            $out[(int) $box['row_id']][] = self::decode($box);
        }
        return $out;
    }

    /** Number of boxes on a page, those inside containers included. */
    public function countForPage(int $pageId): int
    {
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM boxes b JOIN rows r ON r.id = b.row_id WHERE r.page_id = ?',
            [$pageId],
        );
    }

    /** Sum of the widths in a row, optionally ignoring one box. */
    public function widthSum(int $rowId, int $exceptId = 0): int
    {
        return (int) $this->db->value(
            'SELECT COALESCE(SUM(width), 0) FROM boxes WHERE row_id = ? AND id <> ?',
            [$rowId, $exceptId],
        );
    }

    /**
     * @param array<string, mixed> $content
     */
    public function create(int $rowId, string $type, int $width, array $content, ?int $height = null): int
    {
        $position = (int) $this->db->value('SELECT COALESCE(MAX(position), 0) FROM boxes WHERE row_id = ?', [$rowId]) + 1;
        return $this->insertRow([
            'row_id' => $rowId,
            'position' => $position,
            'type' => $type,
            'width' => $width,
            'height' => $height,
            'content' => self::encode($content),
        ]);
    }

    /**
     * @param array<string, mixed> $content
     */
    public function update(int $id, int $width, ?int $height, array $content): void
    {
        $this->updateRow($id, ['width' => $width, 'height' => $height, 'content' => self::encode($content)]);
    }

    /** Delete a box; for a container, also the rows and boxes inside it. */
    public function delete(int $id): void
    {
        $this->db->transaction(function () use ($id): void {
            (new Rows($this->db))->deleteInsideBox($id);
            $this->db->execute('DELETE FROM boxes WHERE id = ?', [$id]);
        });
    }

    /** Move a box left (up) or right (down) within its row and renumber the positions. */
    public function move(int $id, string $direction): void
    {
        $box = $this->find($id);
        if ($box === null) {
            return;
        }
        $this->db->transaction(function () use ($box, $id, $direction): void {
            $ids = array_map('intval', array_column($this->forRow((int) $box['row_id']), 'id'));
            foreach (Rows::reorder($ids, $id, $direction) as $i => $boxId) {
                $this->db->execute('UPDATE boxes SET position = ? WHERE id = ?', [$i + 1, $boxId]);
            }
        });
    }

    /**
     * Pages that contain a box of the given type whose content field equals the id.
     *
     * @return list<array<string, mixed>> distinct pages (id, slug, name)
     */
    public function pagesUsing(string $type, string $field, int $id): array
    {
        $boxes = $this->db->all(
            'SELECT b.content, p.id AS page_id, p.slug, p.name FROM boxes b
             JOIN rows r ON r.id = b.row_id JOIN pages p ON p.id = r.page_id WHERE b.type = ?',
            [$type],
        );
        $pages = [];
        foreach ($boxes as $box) {
            $content = json_decode((string) $box['content'], true);
            if (is_array($content) && (int) ($content[$field] ?? 0) === $id) {
                $pages[(int) $box['page_id']] = ['id' => (int) $box['page_id'], 'slug' => $box['slug'], 'name' => $box['name']];
            }
        }
        return array_values($pages);
    }

    /**
     * @param array<string, mixed> $box
     * @return array<string, mixed>
     */
    private static function decode(array $box): array
    {
        $content = json_decode((string) $box['content'], true);
        $box['content'] = is_array($content) ? $content : [];
        return $box;
    }

    /** @param array<string, mixed> $content */
    private static function encode(array $content): string
    {
        return (string) json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
