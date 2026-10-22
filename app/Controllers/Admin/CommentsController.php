<?php

declare(strict_types=1);

namespace Angelma\Controllers\Admin;

use Angelma\Auth;
use Angelma\Controllers\BlogController;
use Angelma\Repos\Comments;
use Angelma\Request;
use Angelma\Response;

/**
 * Comment moderation for editors and administrators. Approve and delete are also used
 * from the post page; anyone logged in may delete their own comment.
 */
final class CommentsController
{
    public function index(Request $request): Response
    {
        Auth::requireRole('editor');
        $comments = new Comments();
        return view('admin/comments', [
            'title' => t('nav_comments'),
            'nav' => 'comments',
            'pending' => $comments->pending(),
            'approved' => $comments->recentApproved(50),
        ]);
    }

    public function approve(Request $request, int $id): Response
    {
        $comment = self::load($id);
        if (!self::canModerate($comment)) {
            abort(403);
        }
        (new Comments())->approve($id);
        flash('success', t('comment_approved'));
        return self::back($request, $comment);
    }

    public function delete(Request $request, int $id): Response
    {
        $comment = self::load($id);
        $own = $comment['user_id'] !== null && (int) $comment['user_id'] === Auth::id();
        if (!$own && !self::canModerate($comment)) {
            abort(403);
        }
        (new Comments())->delete($id);
        flash('success', t('comment_deleted'));
        return self::back($request, $comment, false);
    }

    /** @return array<string, mixed> */
    private static function load(int $id): array
    {
        Auth::requireRole('user');
        $comment = (new Comments())->find($id);
        if ($comment === null) {
            abort(404);
        }
        return $comment;
    }

    /** @param array<string, mixed> $comment */
    public static function canModerate(array $comment): bool
    {
        return BlogController::canModerate();
    }

    /** Back to the post page or the moderation list, whichever the form came from. */
    private static function back(Request $request, array $comment, bool $anchor = true): Response
    {
        $postPath = '/blog/' . $comment['blog_slug'] . '/' . $comment['post_slug'];
        $return = safe_return_path($request->str('return'), '/admin/comments');
        if ($return === $postPath) {
            return redirect($postPath . ($anchor ? '#comment-' . $comment['id'] : '#comments'));
        }
        return redirect(str_starts_with($return, '/admin/comments') ? '/admin/comments' : $postPath);
    }
}
