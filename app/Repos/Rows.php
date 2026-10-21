<?php

declare(strict_types=1);

namespace Angelma\Repos;

final class Rows extends Repo
{
    protected const TABLE = 'rows';
    public const ALIGNMENTS = ['left', 'center', 'right'];

    /** @return list<array<string, mixed>> */
    public function forPage(int $pageId): array
    {
        return $this->db->all('SELECT * FROM rows WHERE page_id = ? ORDER BY position, id', [$pageId]);
    }

    public function create(int $pageId, string $alignment = 'left'): int
    {
        $position = (int) $this->db->value('SELECT COALESCE(MAX(position), 0) FROM rows WHERE page_id = ?', [$pageId]) + 1;
        return $this->insertRow(['page_id' => $pageId, 'position' => $position, 'alignment' => $alignment]);
    }

    public function setAlignment(int $id, string $alignment): void
    {
        $this->updateRow($id, ['alignment' => $alignment]);
    }

    /** Delete a row and its boxes. */
    public function delete(int $id): void
    {
        $this->db->transaction(function () use ($id): void {
            $this->db->execute('DELETE FROM boxes WHERE row_id = ?', [$id]);
            $this->db->execute('DELETE FROM rows WHERE id = ?', [$id]);
        });
    }

    /** Move a row up or down within its page and renumber the positions. */
    public function move(int $id, string $direction): void
    {
        $row = $this->find($id);
        if ($row === null) {
            return;
        }
        $this->db->transaction(function () use ($row, $id, $direction): void {
            $ids = array_map('intval', array_column($this->forPage((int) $row['page_id']), 'id'));
            foreach (self::reorder($ids, $id, $direction) as $i => $rowId) {
                $this->db->execute('UPDATE rows SET position = ? WHERE id = ?', [$i + 1, $rowId]);
            }
        });
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
}
