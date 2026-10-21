<?php

declare(strict_types=1);

namespace Angelma\Controllers\Admin;

use Angelma\Repos\Boxes;
use Angelma\Repos\Pages;
use Angelma\Request;
use Angelma\Response;

/**
 * Boxes in a row: add, edit, move, delete.
 */
final class BoxesController
{
    /** Content of a new box, per type (PLAN.md section 4). */
    public const DEFAULT_CONTENT = [
        'text' => ['markdown' => ''],
        'image' => ['image_id' => 0, 'alt' => '', 'caption' => '', 'fit' => 'cover'],
        'blog' => ['blog_id' => 0, 'count' => 5, 'show_excerpts' => 1],
        'embed' => ['url' => '', 'html' => ''],
    ];

    public function create(Request $request, int $rowId): Response
    {
        $row = RowsController::load($rowId);
        $pageId = (int) $row['page_id'];
        $type = $request->str('type');
        $width = $request->int('width');
        if (!in_array($type, Boxes::TYPES, true) || !in_array($width, Boxes::WIDTHS, true)) {
            flash('error', t('box_error_type_width'));
            return RowsController::back($pageId, $rowId);
        }

        $boxes = new Boxes();
        $used = $boxes->widthSum($rowId);
        if ($used + $width > 12) {
            flash('error', t('box_error_too_wide', ['width' => $width, 'free' => 12 - $used]));
            return RowsController::back($pageId, $rowId);
        }

        $boxes->create($rowId, $type, $width, self::DEFAULT_CONTENT[$type]);
        (new Pages())->touch($pageId);
        flash('success', t('box_added'));
        return RowsController::back($pageId, $rowId);
    }

    public function move(Request $request, int $id): Response
    {
        $box = self::load($id);
        (new Boxes())->move($id, $request->str('direction') === 'up' ? 'up' : 'down');
        (new Pages())->touch((int) $box['page_id']);
        return RowsController::back((int) $box['page_id'], (int) $box['row_id']);
    }

    public function delete(Request $request, int $id): Response
    {
        $box = self::load($id);
        (new Boxes())->delete($id);
        (new Pages())->touch((int) $box['page_id']);
        flash('success', t('box_deleted'));
        return RowsController::back((int) $box['page_id'], (int) $box['row_id']);
    }

    /**
     * Load a box whose page the current user may edit.
     *
     * @return array<string, mixed>
     */
    public static function load(int $id): array
    {
        $box = (new Boxes())->find($id);
        if ($box === null) {
            abort(404);
        }
        PagesController::load((int) $box['page_id']);
        return $box;
    }
}
