<?php

declare(strict_types=1);

namespace Angelma\Controllers\Admin;

use Angelma\App;
use Angelma\Auth;
use Angelma\Repos\Boxes;
use Angelma\Repos\Pages;
use Angelma\Repos\Rows;
use Angelma\Request;
use Angelma\Response;
use Angelma\Workbench;

/**
 * Page list, new page, page settings and delete. The layout editor (rows and boxes)
 * is shown on the edit screen and handled by RowsController and BoxesController.
 */
final class PagesController
{
    /** Open the workbench (PLAN.md section 6.14), creating it on first use. */
    public function workbench(Request $request): Response
    {
        Auth::requireRole('editor');
        return redirect('/admin/pages/' . Workbench::page()['id']);
    }

    public function index(Request $request): Response
    {
        Auth::requireRole('editor');
        return $this->list();
    }

    public function create(Request $request): Response
    {
        Auth::requireRole('editor');
        $pages = new Pages();
        [$data, $errors] = self::validate($request, $pages, 0);
        if ($errors !== []) {
            return $this->list($data, $errors, 422);
        }
        $data['owner_id'] = Auth::id();
        $id = $pages->create($data);
        flash('success', t('page_created'));
        return redirect('/admin/pages/' . $id);
    }

    public function edit(Request $request, int $id): Response
    {
        $page = self::load($id);
        return $this->editForm($page, $page);
    }

    public function update(Request $request, int $id): Response
    {
        $page = self::load($id);
        $pages = new Pages();
        [$data, $errors] = self::validate($request, $pages, $id);
        if ($errors !== []) {
            return $this->editForm($page, $data, $errors, 422);
        }
        $pages->update($id, $data);
        flash('success', t('page_saved'));
        return redirect('/admin/pages/' . $id);
    }

    public function delete(Request $request, int $id): Response
    {
        $page = self::load($id);
        if ($id === (int) App::setting('home_page_id')) {
            flash('error', t('page_delete_home'));
            return redirect('/admin/pages/' . $id);
        }
        if (Workbench::is($id)) {
            flash('error', t('page_delete_workbench'));
            return redirect('/admin/pages/' . $id);
        }
        (new Pages())->delete($id);
        flash('success', t('page_deleted', ['name' => (string) $page['name']]));
        return redirect('/admin/pages');
    }

    /** Move a page one step up or down in the menu order (administrators only, section 7). */
    public function move(Request $request, int $id): Response
    {
        Auth::requireRole('admin');
        $pages = new Pages();
        if ($pages->find($id) === null) {
            abort(404);
        }
        $pages->moveInMenu($id, $request->str('direction') === 'up' ? 'up' : 'down');
        return redirect('/admin/pages');
    }

    /**
     * Load a page for editing, or stop with 404. Any editor may edit any page
     * (PLAN.md section 7).
     *
     * @return array<string, mixed>
     */
    public static function load(int $id): array
    {
        Auth::requireRole('editor');
        $page = (new Pages())->find($id);
        if ($page === null) {
            abort(404);
        }
        return $page;
    }

    /**
     * @return array{0: array<string, string>, 1: array<string, string>} cleaned data and errors
     */
    private static function validate(Request $request, Pages $pages, int $id): array
    {
        $data = [
            'name' => $request->str('name'),
            'title' => $request->str('title'),
            'slug' => $request->str('slug'),
            'meta_description' => $request->str('meta_description'),
        ];
        $errors = [];
        if ($data['name'] === '' || mb_strlen($data['name']) > 60) {
            $errors['name'] = t('page_error_name');
        }
        if ($data['title'] === '') {
            $data['title'] = $data['name'];
        }
        if (mb_strlen($data['title']) > 150) {
            $errors['title'] = t('page_error_title');
        }
        if (mb_strlen($data['meta_description']) > 300) {
            $errors['meta_description'] = t('page_error_meta');
        }

        if ($data['slug'] === '') {
            // Not typed: derive from the name and make it unique.
            $data['slug'] = slugify($data['name'], static fn (string $s): bool => $pages->slugTaken($s, $id)
                || in_array($s, Pages::RESERVED, true));
        } else {
            $data['slug'] = slugify($data['slug']);
            if (in_array($data['slug'], Pages::RESERVED, true)) {
                $errors['slug'] = t('page_error_slug_reserved', ['slug' => $data['slug']]);
            } elseif ($pages->slugTaken($data['slug'], $id)) {
                $errors['slug'] = t('page_error_slug_taken', ['slug' => $data['slug']]);
            }
        }
        if (!isset($errors['slug']) && !valid_slug($data['slug'])) {
            $errors['slug'] = t('error_slug');
        }
        return [$data, $errors];
    }

    /**
     * @param array<string, string> $input
     * @param array<string, string> $errors
     */
    private function list(array $input = [], array $errors = [], int $status = 200): Response
    {
        return view('admin/pages', [
            'title' => t('nav_pages'),
            'nav' => 'pages',
            'pages' => (new Pages())->all(),
            'homeId' => (int) App::setting('home_page_id'),
            'canOrder' => Auth::isAdmin(),
            'workbenchBoxes' => Workbench::id() > 0 ? (new Boxes())->countForPage(Workbench::id()) : null,
            'input' => $input + ['name' => '', 'title' => '', 'slug' => '', 'meta_description' => ''],
            'errors' => $errors,
        ], $status);
    }

    /**
     * @param array<string, mixed> $page
     * @param array<string, mixed> $input
     * @param array<string, string> $errors
     */
    private function editForm(array $page, array $input, array $errors = [], int $status = 200): Response
    {
        $pageId = (int) $page['id'];
        return view('admin/pages_edit', [
            'title' => t('page_edit_title', ['name' => (string) $page['name']]),
            'nav' => 'pages',
            'page' => $page,
            'input' => $input,
            'errors' => $errors,
            'isHome' => $pageId === (int) App::setting('home_page_id'),
            'isWorkbench' => Workbench::is($pageId),
            'rowsByParent' => (new Rows())->forPageByParent($pageId),
            'boxes' => (new Boxes())->forPage($pageId),
            'workbenchBoxes' => Workbench::id() > 0 && !Workbench::is($pageId) ? (new Boxes())->topLevelForPage(Workbench::id()) : [],
        ], $status);
    }
}
