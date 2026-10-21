<?php

declare(strict_types=1);

namespace Angelma\Controllers;

use Angelma\App;
use Angelma\Markdown;
use Angelma\Repos\Blogs;
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
