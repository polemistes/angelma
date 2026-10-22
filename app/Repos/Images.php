<?php

declare(strict_types=1);

namespace Angelma\Repos;

final class Images extends Repo
{
    protected const TABLE = 'images';

    /**
     * Images, newest first, optionally matching a search on the original file name.
     *
     * @return list<array<string, mixed>>
     */
    public function all(string $search = '', int $limit = 500): array
    {
        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = "LOWER(i.original_name) LIKE ? ESCAPE '\\'";
            $params[] = '%' . addcslashes(mb_strtolower($search), '%_\\') . '%';
        }
        $sql = 'SELECT i.*, u.display_name AS uploader_name FROM images i LEFT JOIN users u ON u.id = i.uploader_id'
            . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY i.id DESC LIMIT ' . max(1, $limit);
        return $this->db->all($sql, $params);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $data = self::only($data, ['filename', 'original_name', 'mime', 'width', 'height', 'size', 'alt', 'uploader_id']);
        $data['alt'] ??= '';
        $data['created_at'] = now();
        return $this->insertRow($data);
    }

    public function updateAlt(int $id, string $alt): void
    {
        $this->updateRow($id, ['alt' => $alt]);
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM images WHERE id = ?', [$id]);
    }
}
