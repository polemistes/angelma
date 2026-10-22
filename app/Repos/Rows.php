<?php

declare(strict_types=1);

namespace Angelma\Repos;

final class Rows extends Repo
{
    protected const TABLE = 'rows';
    public const ALIGNMENTS = ['left', 'center', 'right'];

    /** @return list<array<string, mixed>> every row of a page, nested ones included, in position order */
    public function forPage(int $pageId): array
    {
        return $this->db->all('SELECT * FROM rows WHERE page_id = ? ORDER BY position, id', [$pageId]);
    }

    /**
     * The rows of a page grouped by the container box they live in: key 0 holds the
     * page's own rows, key N the rows inside container box N (PLAN.md section 6.13).
     *
     * @return array<int, list<array<string, mixed>>>
     */
    public function forPageByParent(int $pageId): array
    {
        $out = [];
        foreach ($this->forPage($pageId) as $row) {
            $out[(int) ($row['parent_box_id'] ?? 0)][] = $row;
        }
        return $out;
    }

    /** @return list<array<string, mixed>> the rows directly inside a container box, in order */
    public function forBox(int $boxId): array
    {
        return $this->db->all('SELECT * FROM rows WHERE parent_box_id = ? ORDER BY position, id', [$boxId]);
    }

    /** Add a row at the end of the page, or inside a container box when $parentBoxId is given. */
    public function create(int $pageId, string $alignment = 'left', int $parentBoxId = 0): int
    {
        $siblings = $this->siblings($pageId, $parentBoxId > 0 ? $parentBoxId : null);
        $row = [
            'page_id' => $pageId,
            'position' => $siblings === [] ? 1 : (int) max(array_column($siblings, 'position')) + 1,
            'alignment' => $alignment,
        ];
        if ($parentBoxId > 0) {
            $row['parent_box_id'] = $parentBoxId;
        }
        return $this->insertRow($row);
    }

    public function setAlignment(int $id, string $alignment): void
    {
        $this->updateRow($id, ['alignment' => $alignment]);
    }

    /** Delete a row with its boxes, and everything inside its container boxes. */
    public function delete(int $id): void
    {
        $this->db->transaction(fn () => $this->deleteRow($id));
    }

    /** Delete every row inside a container box, with their boxes, recursively. */
    public function deleteInsideBox(int $boxId): void
    {
        $this->db->transaction(function () use ($boxId): void {
            foreach ($this->forBox($boxId) as $row) {
                $this->deleteRow((int) $row['id']);
            }
        });
    }

    /** Move a row up or down among the rows that share its parent, and renumber the positions. */
    public function move(int $id, string $direction): void
    {
        $row = $this->find($id);
        if ($row === null) {
            return;
        }
        $this->db->transaction(function () use ($row, $id, $direction): void {
            $siblings = $this->siblings((int) $row['page_id'], self::parentOf($row));
            $ids = array_map('intval', array_column($siblings, 'id'));
            foreach (self::reorder($ids, $id, $direction) as $i => $rowId) {
                $this->db->execute('UPDATE rows SET position = ? WHERE id = ?', [$i + 1, $rowId]);
            }
        });
    }

    /**
     * How deep a row is: 0 for a page's own row, 1 for a row inside a container in such
     * a row, and so on. Boxes::MAX_DEPTH limits where containers may be added.
     */
    public function depth(int $rowId): int
    {
        $depth = 0;
        $id = $rowId;
        while (true) {
            $parentRow = $this->db->value(
                'SELECT b.row_id FROM rows r JOIN boxes b ON b.id = r.parent_box_id WHERE r.id = ?',
                [$id],
            );
            if ($parentRow === null) {
                return $depth;
            }
            $depth++;
            $id = (int) $parentRow;
        }
    }

    /**
     * Swap an id with its neighbour in a list.
     *
     * @param list<int> $ids
     * @return list<int>
     */
    public static function reorder(array $ids, int $id, string $direction): array
    {
        $i = array_search($id, $ids, true);
        if ($i === false) {
            return $ids;
        }
        $j = $direction === 'up' ? $i - 1 : $i + 1;
        if ($j < 0 || $j >= count($ids)) {
            return $ids;
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        return $ids;
    }

    /** @return list<array<string, mixed>> the rows that share a parent, in order */
    private function siblings(int $pageId, ?int $parentBoxId): array
    {
        if ($parentBoxId !== null) {
            return $this->forBox($parentBoxId);
        }
        return $this->db->all(
            'SELECT * FROM rows WHERE page_id = ? AND parent_box_id IS NULL ORDER BY position, id',
            [$pageId],
        );
    }

    /** @param array<string, mixed> $row */
    private static function parentOf(array $row): ?int
    {
        return $row['parent_box_id'] === null ? null : (int) $row['parent_box_id'];
    }

    private function deleteRow(int $id): void
    {
        $containers = $this->db->all('SELECT id FROM boxes WHERE row_id = ? AND type = ?', [$id, 'container']);
        foreach ($containers as $box) {
            $this->deleteInsideBox((int) $box['id']);
        }
        $this->db->execute('DELETE FROM boxes WHERE row_id = ?', [$id]);
        $this->db->execute('DELETE FROM rows WHERE id = ?', [$id]);
    }
}
