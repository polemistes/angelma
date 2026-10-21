<?php

declare(strict_types=1);

namespace Angelma\Controllers\Admin;

use Angelma\Auth;
use Angelma\Embed;
use Angelma\Markdown;
use Angelma\Repos\Boxes;
use Angelma\Repos\Images as ImagesRepo;
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

    public const MIN_HEIGHT = 50;
    public const MAX_HEIGHT = 2000;
    public const MAX_MARKDOWN = 50000;

    public function edit(Request $request, int $id): Response
    {
        $box = self::load($id);
        return $this->form($box, $box['content'], (string) ($box['height'] ?? ''), (int) $box['width']);
    }

    public function update(Request $request, int $id): Response
    {
        $box = self::load($id);
        $boxes = new Boxes();
        $errors = [];

        $width = $request->int('width');
        $free = 12 - $boxes->widthSum((int) $box['row_id'], $id);
        if (!in_array($width, Boxes::WIDTHS, true)) {
            $errors['width'] = t('box_error_type_width');
        } elseif ($width > $free) {
            $errors['width'] = t('box_error_too_wide', ['width' => $width, 'free' => $free]);
        }

        $heightText = $request->str('height');
        $height = null;
        if ($heightText !== '') {
            if (!preg_match('/^\d+$/', $heightText) || (int) $heightText < self::MIN_HEIGHT || (int) $heightText > self::MAX_HEIGHT) {
                $errors['height'] = t('box_error_height', ['min' => self::MIN_HEIGHT, 'max' => self::MAX_HEIGHT]);
            } else {
                $height = (int) $heightText;
            }
        }

        $content = $box['content'];
        switch ($box['type']) {
            case 'text':
                $content['markdown'] = $request->input('markdown');
                if (mb_strlen($content['markdown']) > self::MAX_MARKDOWN) {
                    $errors['markdown'] = t('box_error_markdown_long', ['max' => self::MAX_MARKDOWN]);
                }
                break;

            case 'image':
                if (isset($request->post['image_id'])) {
                    $imageId = $request->int('image_id');
                    $image = (new ImagesRepo())->find($imageId);
                    if ($image === null || !Auth::owns((int) $image['uploader_id'])) {
                        $errors['image_id'] = t('box_error_image');
                    } else {
                        $content['image_id'] = $imageId;
                    }
                }
                $content['alt'] = $request->str('alt');
                $content['caption'] = $request->str('caption');
                $content['fit'] = $request->str('fit') === 'contain' ? 'contain' : 'cover';
                foreach (['alt', 'caption'] as $field) {
                    if (mb_strlen($content[$field]) > 300) {
                        $errors[$field] = t('box_error_300');
                    }
                }
                break;

            case 'embed':
                $content['url'] = $request->str('url');
                if ($content['url'] !== '' && (!Embed::isHttpUrl($content['url']) || strlen($content['url']) > 2000)) {
                    $errors['url'] = t('box_error_url');
                }
                if (array_key_exists('html', $request->post)) {
                    // Raw HTML is for administrators only (PLAN.md section 6.6).
                    if (!Auth::isAdmin()) {
                        abort(403);
                    }
                    $content['html'] = trim($request->input('html'));
                }
                break;
        }

        if ($errors !== []) {
            return $this->form($box, $content, $heightText, $width, $errors, 422);
        }

        $boxes->update($id, $width, $height, $content);
        (new Pages())->touch((int) $box['page_id']);
        flash('success', t('box_saved'));
        return RowsController::back((int) $box['page_id'], (int) $box['row_id']);
    }

    /** Render Markdown for the live preview in the text box form (admin.js). */
    public function preview(Request $request): Response
    {
        Auth::requireRole('editor');
        return new Response(Markdown::render($request->input('markdown')), 200, [
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }

    /**
     * @param array<string, mixed> $box
     * @param array<string, mixed> $content
     * @param array<string, string> $errors
     */
    private function form(array $box, array $content, string $height, int $width, array $errors = [], int $status = 200): Response
    {
        $page = (new Pages())->find((int) $box['page_id']);
        $free = 12 - (new Boxes())->widthSum((int) $box['row_id'], (int) $box['id']);
        $extra = [];
        if ($box['type'] === 'image') {
            $repo = new ImagesRepo();
            $extra['pickerImages'] = $repo->all(Auth::isAdmin() ? null : Auth::id(), '', ImagesController::PICKER_LIMIT);
            $extra['selectedImage'] = !empty($content['image_id']) ? $repo->find((int) $content['image_id']) : null;
            $extra['maxBytes'] = \Angelma\Images::maxUploadBytes();
        }
        return view('admin/box_edit', $extra + [
            'title' => t('box_edit_title', ['type' => t('box_type_' . $box['type'])]),
            'nav' => 'pages',
            'box' => $box,
            'page' => $page,
            'content' => $content,
            'height' => $height,
            'width' => $width,
            'widths' => array_values(array_filter(Boxes::WIDTHS, static fn (int $w): bool => $w <= $free)),
            'errors' => $errors,
        ], $status);
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
