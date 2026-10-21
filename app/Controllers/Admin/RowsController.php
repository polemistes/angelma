<?php

declare(strict_types=1);

namespace Angelma\Controllers\Admin;

use Angelma\Repos\Pages;
use Angelma\Repos\Rows;
use Angelma\Request;
use Angelma\Response;

/**
 * Rows of a page: add, move, delete, alignment. All POST.
 */
final class RowsController
{
    public function create(Request $request, int $pageId): Response
    {
        PagesController::load($pageId);
        $rowId = (new Rows())->create($pageId, 'left');
        (new Pages())->touch($pageId);
        flash('success', t('row_added'));
        return self::back($pageId, $rowId);
    }

    public function move(Request $request, int $id): Response
    {
        $row = self::load($id);
        $direction = $request->str('direction') === 'up' ? 'up' : 'down';
        (new Rows())->move($id, $direction);
        (new Pages())->touch((int) $row['page_id']);
        return self::back((int) $row['page_id'], $id);
    }

    public function delete(Request $request, int $id): Response
    {
        $row = self::load($id);
        (new Rows())->delete($id);
        (new Pages())->touch((int) $row['page_id']);
        flash('success', t('row_deleted'));
        return self::back((int) $row['page_id']);
    }

    public function alignment(Request $request, int $id): Response
    {
        $row = self::load($id);
        $alignment = $request->str('alignment');
        if (!in_array($alignment, Rows::ALIGNMENTS, true)) {
            abort(400);
        }
        (new Rows())->setAlignment($id, $alignment);
        (new Pages())->touch((int) $row['page_id']);
        flash('success', t('row_alignment_saved'));
        return self::back((int) $row['page_id'], $id);
    }

    /**
     * Load a row whose page the current user may edit.
     *
     * @return array<string, mixed>
     */
    public static function load(int $id): array
    {
        $row = (new Rows())->find($id);
        if ($row === null) {
            abort(404);
        }
        PagesController::load((int) $row['page_id']);
        return $row;
    }

    public static function back(int $pageId, int $rowId = 0): Response
    {
        return redirect('/admin/pages/' . $pageId . ($rowId > 0 ? '#row-' . $rowId : '#layout'));
    }
}
