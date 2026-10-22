<?php

declare(strict_types=1);

namespace Angelma\Controllers\Admin;

use Angelma\Auth;
use Angelma\Repos\Posts;
use Angelma\Request;
use Angelma\Response;

/**
 * Create, edit and delete posts. Admins may work in any blog, editors in their own.
 */
final class PostsController
{
    /** Post slugs that would clash with blog URLs. */
    public const RESERVED = ['feed'];
    public const MAX_BODY = 100000;

    public function new(Request $request, int $blogId): Response
    {
        $blog = BlogsController::load($blogId);
        return $this->form($blog, null, [
            'title' => '', 'slug' => '', 'body_markdown' => '', 'status' => 'draft',
            'comments_mode' => 'anyone', 'published_at' => '',
        ]);
    }

    public function create(Request $request, int $blogId): Response
    {
        $blog = BlogsController::load($blogId);
        $posts = new Posts();
        [$data, $errors, $input] = self::validate($request, $posts, $blogId, null);
        if ($errors !== []) {
            return $this->form($blog, null, $input, $errors, 422);
        }
        $data['blog_id'] = $blogId;
        $data['author_id'] = Auth::id();
        $id = $posts->create($data);
        flash('success', $data['status'] === 'published' ? t('post_published') : t('post_saved_draft'));
        return redirect('/admin/posts/' . $id);
    }

    public function edit(Request $request, int $id): Response
    {
        $post = self::load($id);
        $input = $post;
        $input['published_at'] = utc_to_local_input($post['published_at']);
        return $this->form(self::blogOf($post), $post, $input);
    }

    public function update(Request $request, int $id): Response
    {
        $post = self::load($id);
        $posts = new Posts();
        [$data, $errors, $input] = self::validate($request, $posts, (int) $post['blog_id'], $post);
        if ($errors !== []) {
            return $this->form(self::blogOf($post), $post, $input, $errors, 422);
        }
        $posts->update($id, $data);
        flash('success', $data['status'] === 'published' ? t('post_saved_published') : t('post_saved_draft'));
        return redirect('/admin/posts/' . $id);
    }

    public function delete(Request $request, int $id): Response
    {
        $post = self::load($id);
        (new Posts())->delete($id);
        flash('success', t('post_deleted', ['title' => (string) $post['title']]));
        return redirect('/admin/blogs/' . $post['blog_id']);
    }

    /**
     * A post in a blog the current user may manage.
     *
     * @return array<string, mixed>
     */
    public static function load(int $id): array
    {
        Auth::requireRole('editor');
        $post = (new Posts())->find($id);
        if ($post === null) {
            abort(404);
        }
        return $post;
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private static function blogOf(array $post): array
    {
        return ['id' => $post['blog_id'], 'slug' => $post['blog_slug'], 'title' => $post['blog_title']];
    }

    /**
     * @param array<string, mixed>|null $post the existing post when editing
     * @return array{0: array<string, mixed>, 1: array<string, string>, 2: array<string, string>}
     *         data to save, errors, and the raw input for redisplay
     */
    private static function validate(Request $request, Posts $posts, int $blogId, ?array $post): array
    {
        $postId = $post === null ? 0 : (int) $post['id'];
        $input = [
            'title' => $request->str('title'),
            'slug' => $request->str('slug'),
            'body_markdown' => $request->input('body_markdown'),
            'status' => $request->str('status'),
            'comments_mode' => $request->str('comments_mode'),
            'published_at' => $request->str('published_at'),
        ];
        $errors = [];
        $data = [
            'title' => $input['title'],
            'body_markdown' => $input['body_markdown'],
            'status' => in_array($input['status'], Posts::STATUSES, true) ? $input['status'] : 'draft',
            'comments_mode' => in_array($input['comments_mode'], Posts::COMMENT_MODES, true) ? $input['comments_mode'] : 'closed',
        ];
        if ($data['title'] === '' || mb_strlen($data['title']) > 200) {
            $errors['title'] = t('post_error_title');
        }
        if (mb_strlen($data['body_markdown']) > self::MAX_BODY) {
            $errors['body_markdown'] = t('box_error_markdown_long', ['max' => self::MAX_BODY]);
        }

        $taken = static fn (string $s): bool => $posts->slugTaken($blogId, $s, $postId) || in_array($s, self::RESERVED, true);
        if ($input['slug'] === '') {
            $data['slug'] = slugify($data['title'], $taken);
        } else {
            $data['slug'] = slugify($input['slug']);
            if ($taken($data['slug'])) {
                $errors['slug'] = t('post_error_slug_taken', ['slug' => $data['slug']]);
            }
        }
        if (!isset($errors['slug']) && !valid_slug($data['slug'])) {
            $errors['slug'] = t('error_slug');
        }

        // Publish date: kept as it is unless changed; set to now when first published.
        $existing = $post['published_at'] ?? null;
        if ($input['published_at'] !== '') {
            if ($existing !== null && $input['published_at'] === utc_to_local_input($existing)) {
                $data['published_at'] = $existing;
            } else {
                $data['published_at'] = local_input_to_utc($input['published_at']);
                if ($data['published_at'] === null) {
                    $errors['published_at'] = t('post_error_date');
                }
            }
        } else {
            $data['published_at'] = $existing ?? ($data['status'] === 'published' ? now() : null);
        }

        if (!isset($errors['slug'])) {
            $input['slug'] = (string) $data['slug'];
        }
        return [$data, $errors, $input];
    }

    /**
     * @param array<string, mixed> $blog
     * @param array<string, mixed>|null $post
     * @param array<string, mixed> $input
     * @param array<string, string> $errors
     */
    private function form(array $blog, ?array $post, array $input, array $errors = [], int $status = 200): Response
    {
        return view('admin/posts_edit', [
            'title' => $post === null ? t('post_new_in', ['blog' => (string) $blog['title']]) : t('post_edit_title'),
            'nav' => 'blogs',
            'blog' => $blog,
            'post' => $post,
            'input' => $input,
            'errors' => $errors,
        ], $status);
    }
}
