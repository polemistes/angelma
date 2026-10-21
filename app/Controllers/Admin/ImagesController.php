<?php

declare(strict_types=1);

namespace Angelma\Controllers\Admin;

use Angelma\App;
use Angelma\Auth;
use Angelma\ImageException;
use Angelma\Images;
use Angelma\Repos\Boxes;
use Angelma\Repos\Images as ImagesRepo;
use Angelma\Request;
use Angelma\Response;

/**
 * Image library: upload, alt text, delete. Admins see all images, editors their own.
 */
final class ImagesController
{
    public const MAX_ALT = 300;
    public const PICKER_LIMIT = 60;

    public function index(Request $request): Response
    {
        Auth::requireRole('editor');
        return view('admin/images', [
            'title' => t('nav_images'),
            'nav' => 'images',
            'images' => (new ImagesRepo())->all(Auth::isAdmin() ? null : Auth::id()),
            'maxBytes' => Images::maxUploadBytes(),
            'showUploader' => Auth::isAdmin(),
        ]);
    }

    /** Image list for the picker in the image box form, searchable by file name. */
    public function picker(Request $request): Response
    {
        Auth::requireRole('editor');
        $images = (new ImagesRepo())->all(Auth::isAdmin() ? null : Auth::id(), $request->query('q'), self::PICKER_LIMIT);
        return new Response(render('partials/image_picker_list', [
            'images' => $images,
            'selected' => $request->int('selected'),
            'field' => $request->query('field'),
        ]));
    }

    public function upload(Request $request): Response
    {
        Auth::requireRole('editor');
        $boxId = $request->int('box_id');
        if ($boxId > 0) {
            return $this->uploadForBox($request, $boxId);
        }
        $files = self::uploadedFiles($request->files['images'] ?? null);
        if ($files === []) {
            flash('error', t('image_error_no_file'));
            return redirect('/admin/images');
        }

        $stored = 0;
        foreach ($files as $file) {
            try {
                self::storeOne($file);
                $stored++;
            } catch (ImageException $e) {
                flash('error', t('image_upload_failed', ['name' => (string) ($file['name'] ?? ''), 'reason' => $e->getMessage()]));
            }
        }
        if ($stored > 0) {
            flash('success', t('image_uploaded', ['count' => $stored]));
        }
        return redirect('/admin/images');
    }

    /** Upload from the image box form: store the image and put it in the box. */
    private function uploadForBox(Request $request, int $boxId): Response
    {
        $box = BoxesController::load($boxId);
        $files = self::uploadedFiles($request->files['images'] ?? null);
        if ($box['type'] !== 'image' || $files === []) {
            flash('error', t('image_error_no_file'));
            return redirect('/admin/boxes/' . $boxId);
        }
        try {
            $image = self::storeOne($files[0]);
        } catch (ImageException $e) {
            flash('error', t('image_upload_failed', ['name' => (string) ($files[0]['name'] ?? ''), 'reason' => $e->getMessage()]));
            return redirect('/admin/boxes/' . $boxId);
        }
        $content = $box['content'];
        $content['image_id'] = (int) $image['id'];
        (new Boxes())->update($boxId, (int) $box['width'], $box['height'] === null ? null : (int) $box['height'], $content);
        flash('success', t('image_uploaded_into_box', ['name' => (string) $image['original_name']]));
        return redirect('/admin/boxes/' . $boxId);
    }

    public function alt(Request $request, int $id): Response
    {
        $image = self::load($id);
        $alt = $request->str('alt');
        if (mb_strlen($alt) > self::MAX_ALT) {
            flash('error', t('image_error_alt', ['max' => self::MAX_ALT]));
        } else {
            (new ImagesRepo())->updateAlt($id, $alt);
            flash('success', t('image_alt_saved', ['name' => (string) $image['original_name']]));
        }
        return redirect('/admin/images#image-' . $id);
    }

    public function delete(Request $request, int $id): Response
    {
        $image = self::load($id);
        $usedBy = array_map(static fn (array $p): string => '"' . $p['name'] . '"', (new Boxes())->pagesUsing('image', 'image_id', $id));
        if ((int) App::setting('logo_image_id') === $id) {
            $usedBy[] = t('image_used_logo');
        }
        if ($usedBy !== []) {
            flash('error', t('image_in_use', ['name' => (string) $image['original_name'], 'pages' => implode(', ', $usedBy)]));
            return redirect('/admin/images#image-' . $id);
        }

        (new ImagesRepo())->delete($id);
        Images::deleteFiles(App::$root . '/uploads', (string) $image['filename']);
        flash('success', t('image_deleted', ['name' => (string) $image['original_name']]));
        return redirect('/admin/images');
    }

    /**
     * Store one uploaded file and add it to the library.
     *
     * @param array<string, mixed> $file
     * @return array<string, mixed> the new image row
     * @throws ImageException
     */
    public static function storeOne(array $file): array
    {
        $data = (new Images(App::$root . '/uploads'))->storeUpload($file);
        $data['uploader_id'] = Auth::id();
        $repo = new ImagesRepo();
        $id = $repo->create($data);
        return (array) $repo->find($id);
    }

    /**
     * An image the current user may change: admins any, editors their own.
     *
     * @return array<string, mixed>
     */
    private static function load(int $id): array
    {
        Auth::requireRole('editor');
        $image = (new ImagesRepo())->find($id);
        if ($image === null) {
            abort(404);
        }
        Auth::requireOwner((int) $image['uploader_id']);
        return $image;
    }

    /**
     * Normalise a single or multiple file field of $_FILES into a list of files.
     *
     * @return list<array<string, mixed>>
     */
    public static function uploadedFiles(mixed $field): array
    {
        if (!is_array($field) || !isset($field['name'])) {
            return [];
        }
        if (!is_array($field['name'])) {
            return ($field['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$field];
        }
        $files = [];
        foreach (array_keys($field['name']) as $i) {
            $file = [];
            foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $key) {
                $file[$key] = $field[$key][$i] ?? null;
            }
            if (is_string($file['name']) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $files[] = $file;
            }
        }
        return $files;
    }
}
