<?php

declare(strict_types=1);

namespace Angelma\Controllers\Admin;

use Angelma\Auth;
use Angelma\Repos\Blogs;
use Angelma\Repos\Boxes;
use Angelma\Repos\Posts;
use Angelma\Request;
use Angelma\Response;

/**
 * Blogs: list, create, edit, delete. Admins see all blogs, editors their own.
 */
final class BlogsController
{
    public function index(Request $request): Response
    {
        Auth::requireRole('editor');
        return $this->list();
    }

    public function create(Request $request): Response
    {
        Auth::requireRole('editor');
        $blogs = new Blogs();
        [$data, $errors] = self::validate($request, $blogs, 0);
        if ($errors !== []) {
            return $this->list($data, $errors, 422);
        }
        $data['owner_id'] = Auth::id();
        $id = $blogs->create($data);
        flash('success', t('blog_created'));
        return redirect('/admin/blogs/' . $id);
    }

    public function edit(Request $request, int $id): Response
    {
        $blog = self::load($id);
        return $this->editForm($blog, $blog);
    }

    public function update(Request $request, int $id): Response
    {
        $blog = self::load($id);
        $blogs = new Blogs();
        [$data, $errors] = self::validate($request, $blogs, $id);
        if ($errors !== []) {
            return $this->editForm($blog, $data, $errors, 422);
        }
        $blogs->update($id, $data);
        flash('success', t('blog_saved'));
        return redirect('/admin/blogs/' . $id);
    }

    public function delete(Request $request, int $id): Response
    {
        $blog = self::load($id);
        $blogs = new Blogs();
        if ($blogs->postCount($id) > 0) {
            flash('error', t('blog_delete_has_posts'));
            return redirect('/admin/blogs/' . $id);
        }
        $pages = (new Boxes())->pagesUsing('blog', 'blog_id', $id);
        if ($pages !== []) {
            $names = implode(', ', array_map(static fn (array $p): string => '"' . $p['name'] . '"', $pages));
            flash('error', t('blog_delete_in_use', ['pages' => $names]));
            return redirect('/admin/blogs/' . $id);
        }
        $blogs->delete($id);
        flash('success', t('blog_deleted', ['title' => (string) $blog['title']]));
        return redirect('/admin/blogs');
    }

    /**
     * A blog the current user may manage: admins any, editors their own.
     *
     * @return array<string, mixed>
     */
    public static function load(int $id): array
    {
        Auth::requireRole('editor');
        $blog = (new Blogs())->find($id);
        if ($blog === null) {
            abort(404);
        }
        Auth::requireOwner((int) $blog['owner_id']);
        return $blog;
    }

    /**
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    private static function validate(Request $request, Blogs $blogs, int $id): array
    {
        $data = [
            'title' => $request->str('title'),
            'slug' => $request->str('slug'),
            'description' => $request->str('description'),
        ];
        $errors = [];
        if ($data['title'] === '' || mb_strlen($data['title']) > 100) {
            $errors['title'] = t('blog_error_title');
        }
        if (mb_strlen($data['description']) > 1000) {
            $errors['description'] = t('blog_error_description');
        }
        if ($data['slug'] === '') {
            $data['slug'] = slugify($data['title'], static fn (string $s): bool => $blogs->slugTaken($s, $id));
        } else {
            $data['slug'] = slugify($data['slug']);
            if ($blogs->slugTaken($data['slug'], $id)) {
                $errors['slug'] = t('blog_error_slug_taken', ['slug' => $data['slug']]);
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
        return view('admin/blogs', [
            'title' => t('nav_blogs'),
            'nav' => 'blogs',
            'blogs' => (new Blogs())->all(Auth::isAdmin() ? null : Auth::id()),
            'input' => $input + ['title' => '', 'slug' => '', 'description' => ''],
            'errors' => $errors,
        ], $status);
    }

    /**
     * @param array<string, mixed> $blog
     * @param array<string, mixed> $input
     * @param array<string, string> $errors
     */
    private function editForm(array $blog, array $input, array $errors = [], int $status = 200): Response
    {
        return view('admin/blogs_edit', [
            'title' => t('blog_edit_title', ['title' => (string) $blog['title']]),
            'nav' => 'blogs',
            'blog' => $blog,
            'input' => $input,
            'errors' => $errors,
            'posts' => (new Posts())->forBlog((int) $blog['id']),
        ], $status);
    }
}
