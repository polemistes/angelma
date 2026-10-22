<?php

declare(strict_types=1);

namespace Angelma\Controllers;

use Angelma\App;
use Angelma\Auth;
use Angelma\Markdown;
use Angelma\Repos\Blogs;
use Angelma\Repos\Comments;
use Angelma\Repos\Posts;
use Angelma\Request;
use Angelma\Response;

/**
 * Public blog pages: the post list, the RSS feed, single posts and comments.
 */
final class BlogController
{
    public const PER_PAGE = 10;
    public const FEED_SIZE = 20;

    // Comment spam controls (PLAN.md section 6.7).
    public const COMMENT_MIN_SECONDS = 3;
    public const COMMENT_MAX_PER_WINDOW = 5;
    public const COMMENT_WINDOW_SECONDS = 600;
    public const COMMENT_MAX_LENGTH = 4000;
    public const COMMENT_MAX_URLS = 2;
    public const COMMENT_MAX_NAME = 60;

    public function index(Request $request, string $blogSlug): Response
    {
        $blog = self::blog($blogSlug);
        $posts = new Posts();
        $total = $posts->countPublished((int) $blog['id']);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = $request->int('page', 1);
        if ($page < 1 || $page > $pages) {
            abort(404);
        }

        return view('site/blog_index', [
            'title' => (string) $blog['title'],
            'blog' => $blog,
            'posts' => $posts->publishedForBlog((int) $blog['id'], self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'page' => $page,
            'pages' => $pages,
            'feed_url' => url('/blog/' . $blog['slug'] . '/feed'),
            'feed_title' => (string) $blog['title'],
        ]);
    }

    /** One published post with its comments. Drafts are not public. */
    public function post(Request $request, string $blogSlug, string $postSlug): Response
    {
        [$blog, $post] = self::publishedPost($blogSlug, $postSlug);
        return $this->showPost($blog, $post);
    }

    /** POST target of the comment form. */
    public function comment(Request $request, string $blogSlug, string $postSlug): Response
    {
        [$blog, $post] = self::publishedPost($blogSlug, $postSlug);
        $user = Auth::user();
        $mode = (string) $post['comments_mode'];
        if ($mode === 'closed' || ($mode === 'users' && $user === null)) {
            abort(403);
        }

        $postUrl = '/blog/' . $blog['slug'] . '/' . $post['slug'];
        $input = ['author_name' => $request->str('author_name'), 'body' => trim($request->input('body'))];
        $comments = new Comments();

        // Bots: the honeypot must stay empty and the form must be at least 3 seconds old.
        if ($request->str('website') !== '' || !self::formAgeOk($request->str('form_time'))) {
            return $this->showPost($blog, $post, $input, ['form' => t('comment_rejected')], 422);
        }

        $ipHash = $request->ipHash();
        $since = gmdate('Y-m-d\TH:i:s\Z', time() - self::COMMENT_WINDOW_SECONDS);
        if ($comments->countSince($ipHash, $since) >= self::COMMENT_MAX_PER_WINDOW) {
            return $this->showPost($blog, $post, $input, ['form' => t('comment_too_many')], 429);
        }

        $errors = [];
        if ($user === null) {
            if ($input['author_name'] === '' || mb_strlen($input['author_name']) > self::COMMENT_MAX_NAME) {
                $errors['author_name'] = t('comment_error_name', ['max' => self::COMMENT_MAX_NAME]);
            }
            if (self::countUrls($input['body']) > self::COMMENT_MAX_URLS) {
                $errors['body'] = t('comment_error_urls', ['max' => self::COMMENT_MAX_URLS]);
            }
        }
        if ($input['body'] === '') {
            $errors['body'] = t('comment_error_empty');
        } elseif (mb_strlen($input['body']) > self::COMMENT_MAX_LENGTH) {
            $errors['body'] = t('comment_error_long', ['max' => self::COMMENT_MAX_LENGTH]);
        }
        if ($errors !== []) {
            return $this->showPost($blog, $post, $input, $errors, 422);
        }

        $pending = $user === null && App::setting('moderate_anonymous', '1') === '1';
        $id = $comments->create([
            'post_id' => (int) $post['id'],
            'user_id' => $user === null ? null : (int) $user['id'],
            'author_name' => $user === null ? $input['author_name'] : (string) $user['display_name'],
            'body' => $input['body'],
            'status' => $pending ? 'pending' : 'approved',
            'ip_hash' => $ipHash,
        ]);
        flash('success', $pending ? t('comment_pending') : t('comment_published'));
        return redirect($postUrl . '#comment-' . $id);
    }

    /**
     * @param array<string, mixed> $blog
     * @param array<string, mixed> $post
     * @param array<string, string> $input
     * @param array<string, string> $errors
     */
    private function showPost(array $blog, array $post, array $input = [], array $errors = [], int $status = 200): Response
    {
        $moderator = self::canModerate();
        return view('site/post', [
            'title' => (string) $post['title'],
            'meta_description' => Markdown::firstParagraph((string) $post['body_markdown'], 160),
            'blog' => $blog,
            'post' => $post,
            'comments' => (new Comments())->forPost((int) $post['id'], $moderator),
            'moderator' => $moderator,
            'user' => Auth::user(),
            'input' => $input + ['author_name' => '', 'body' => ''],
            'errors' => $errors,
            'formTime' => self::formTime(),
            'feed_url' => url('/blog/' . $blog['slug'] . '/feed'),
            'feed_title' => (string) $blog['title'],
        ], $status);
    }

    /** May the current user see pending comments and moderate them? Editors and admins may. */
    public static function canModerate(): bool
    {
        return Auth::can('editor');
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>} blog and post, or 404
     */
    private static function publishedPost(string $blogSlug, string $postSlug): array
    {
        $blog = self::blog($blogSlug);
        $post = (new Posts())->findBySlug((int) $blog['id'], $postSlug);
        if ($post === null || $post['status'] !== 'published') {
            abort(404);
        }
        return [$blog, $post];
    }

    /** Signed time stamp for the comment form, so the 3 second rule cannot be faked. */
    public static function formTime(?int $time = null): string
    {
        $time ??= time();
        return $time . '.' . substr(hash_hmac('sha256', 'comment-form:' . $time, App::secret()), 0, 16);
    }

    public static function formAgeOk(string $value, ?int $now = null): bool
    {
        if (!preg_match('/^(\d+)\.([a-f0-9]{16})$/', $value, $m)) {
            return false;
        }
        $time = (int) $m[1];
        if (!hash_equals(substr(hash_hmac('sha256', 'comment-form:' . $time, App::secret()), 0, 16), $m[2])) {
            return false;
        }
        $age = ($now ?? time()) - $time;
        return $age >= self::COMMENT_MIN_SECONDS && $age <= 86400;
    }

    /** Number of links in a text: http(s):// addresses and www. names. */
    public static function countUrls(string $text): int
    {
        return (int) preg_match_all('#(https?://|\bwww\.)\S+#i', $text);
    }

    /** RSS 2.0 feed of the newest published posts. */
    public function feed(Request $request, string $blogSlug): Response
    {
        $blog = self::blog($blogSlug);
        $posts = (new Posts())->publishedForBlog((int) $blog['id'], self::FEED_SIZE);
        $x = static fn (string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $rssDate = static fn (?string $utc): string => (new \DateTimeImmutable($utc ?? 'now', new \DateTimeZone('UTC')))->format(DATE_RSS);

        $blogUrl = absolute_url('/blog/' . $blog['slug']);
        $feedUrl = absolute_url('/blog/' . $blog['slug'] . '/feed');
        $description = (string) $blog['description'] !== '' ? (string) $blog['description'] : (string) $blog['title'];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/">' . "\n"
            . "<channel>\n"
            . '<title>' . $x($blog['title'] . ' - ' . App::setting('site_title')) . "</title>\n"
            . '<link>' . $x($blogUrl) . "</link>\n"
            . '<description>' . $x($description) . "</description>\n"
            . "<language>en</language>\n"
            . '<atom:link href="' . $x($feedUrl) . '" rel="self" type="application/rss+xml"/>' . "\n";
        if ($posts !== []) {
            $xml .= '<lastBuildDate>' . $rssDate($posts[0]['published_at']) . "</lastBuildDate>\n";
        }
        foreach ($posts as $post) {
            $link = absolute_url('/blog/' . $blog['slug'] . '/' . $post['slug']);
            $xml .= "<item>\n"
                . '<title>' . $x((string) $post['title']) . "</title>\n"
                . '<link>' . $x($link) . "</link>\n"
                . '<guid isPermaLink="true">' . $x($link) . "</guid>\n"
                . '<pubDate>' . $rssDate($post['published_at']) . "</pubDate>\n"
                . '<dc:creator>' . $x((string) $post['author_name']) . "</dc:creator>\n"
                . '<description>' . $x(self::absoluteLinks(Markdown::render((string) $post['body_markdown']))) . "</description>\n"
                . "</item>\n";
        }
        $xml .= "</channel>\n</rss>\n";

        return new Response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=utf-8']);
    }

    /**
     * A blog by slug, or 404.
     *
     * @return array<string, mixed>
     */
    public static function blog(string $slug): array
    {
        $blog = (new Blogs())->findBySlug($slug);
        if ($blog === null) {
            abort(404);
        }
        return $blog;
    }

    /** Make site-relative links and images absolute, as feed readers need. */
    private static function absoluteLinks(string $html): string
    {
        $origin = App::$request?->origin() ?? '';
        return (string) preg_replace('#\b(href|src)="/(?!/)#', '$1="' . $origin . '/', $html);
    }
}
