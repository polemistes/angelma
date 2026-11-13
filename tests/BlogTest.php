<?php

declare(strict_types=1);

namespace Angelma\Tests;

use Angelma\App;
use Angelma\Auth;
use Angelma\Controllers\BlogController;
use Angelma\HttpException;
use Angelma\Repos\Blogs;
use Angelma\Repos\Boxes;
use Angelma\Repos\Comments;
use Angelma\Repos\Posts;
use Angelma\Request;

final class BlogTest extends DbTestCase
{
    private int $blogId;
    private int $ownerId;

    protected function setUp(): void
    {
        parent::setUp();
        $owner = $this->ownerId = $this->makeUser('writer', 'editor');
        $this->blogId = (new Blogs($this->db))->create(['slug' => 'news', 'title' => 'News & <Views>', 'owner_id' => $owner]);
        $posts = new Posts($this->db);
        for ($i = 1; $i <= 12; $i++) {
            $posts->create([
                'blog_id' => $this->blogId, 'slug' => 'p' . $i, 'title' => "Post $i <&>",
                'body_markdown' => "Body of [post $i](/x) & more", 'status' => 'published',
                'comments_mode' => 'closed', 'author_id' => $owner,
                'published_at' => sprintf('2026-10-%02dT23:30:00Z', $i),
            ]);
        }
        $posts->create(['blog_id' => $this->blogId, 'slug' => 'draft', 'title' => 'Draft', 'body_markdown' => '',
            'status' => 'draft', 'comments_mode' => 'closed', 'author_id' => $owner, 'published_at' => null]);
        App::$request = new Request('GET', '/blog/news', [], [], [], ['HTTP_HOST' => 'example.com', 'HTTPS' => 'on']);
    }

    protected function tearDown(): void
    {
        Auth::reset();
        App::forgetSettings();
        App::$request = null;
        parent::tearDown();
    }

    public function testPaginationShowsTenThenTwo(): void
    {
        $controller = new BlogController();
        $page1 = $controller->index(new Request('GET', '/blog/news'), 'news')->body;
        $page2 = $controller->index(new Request('GET', '/blog/news', ['page' => '2']), 'news')->body;
        $this->assertSame(10, substr_count($page1, 'class="post-summary"'));
        $this->assertSame(2, substr_count($page2, 'class="post-summary"'));
        $this->assertStringContainsString('Post 12 &lt;&amp;&gt;', $page1);
        $this->assertStringNotContainsString('Draft', $page1 . $page2);

        $this->expectException(HttpException::class);
        $controller->index(new Request('GET', '/blog/news', ['page' => '3']), 'news');
    }

    public function testBlogBoxShowsTitlesFirstParagraphOrWholeText(): void
    {
        (new Posts($this->db))->create([
            'blog_id' => $this->blogId, 'slug' => 'long', 'title' => 'Long post',
            'body_markdown' => "Opening with a [link](/y).\n\n# Part two\n\nClosing <b>words</b>.", 'status' => 'published',
            'comments_mode' => 'closed', 'author_id' => $this->ownerId, 'published_at' => '2026-10-20T10:00:00Z',
        ]);
        $box = fn (array $content): string => partial('box_blog', ['content' => ['blog_id' => $this->blogId, 'count' => 3] + $content]);

        $none = $box(['text' => 'none']);
        $this->assertSame(3, substr_count($none, 'class="blog-box-post"'));
        $this->assertStringContainsString('href="/blog/news/long"', $none);
        $this->assertStringNotContainsString('Opening', $none);

        $first = $box(['text' => 'first']);
        $this->assertStringContainsString('<p class="post-excerpt">Opening with a link.</p>', $first);
        $this->assertStringNotContainsString('Closing', $first);
        $this->assertStringNotContainsString('post-body', $first);

        $full = $box(['text' => 'full']);
        $this->assertSame(3, substr_count($full, 'class="prose post-body"'));
        $this->assertStringContainsString('Opening with a <a href="/y">link</a>.', $full);
        $this->assertStringContainsString('<h2>Part two</h2>', $full);
        // Posts take no HTML: the tags are dropped, as on the post page.
        $this->assertStringContainsString('<p>Closing words.</p>', $full);
        $this->assertStringNotContainsString('post-excerpt', $full);
        $this->assertStringContainsString('Body of <a href="/x">post 12</a> &amp; more', $full);
        $this->assertStringNotContainsString('Draft', $full);
    }

    public function testBlogBoxReadsBoxesSavedBeforeTheTextChoice(): void
    {
        $this->assertSame('first', Boxes::blogText(['show_excerpts' => 1]));
        $this->assertSame('none', Boxes::blogText(['show_excerpts' => 0]));
        $this->assertSame('first', Boxes::blogText([]));
        $this->assertSame('full', Boxes::blogText(['text' => 'full', 'show_excerpts' => 0]));
        $this->assertSame('first', Boxes::blogText(['text' => 'everything']));

        $old = partial('box_blog', ['content' => ['blog_id' => $this->blogId, 'count' => 2, 'show_excerpts' => 0]]);
        $this->assertSame(2, substr_count($old, 'class="blog-box-post"'));
        $this->assertStringNotContainsString('post-excerpt', $old);
        $this->assertStringNotContainsString('post-body', $old);
    }

    public function testClosedPostWithoutCommentsSaysNothingAboutComments(): void
    {
        $posts = new Posts($this->db);
        $comments = new Comments($this->db);
        $controller = new BlogController();
        $page = static fn (string $slug): string => $controller->post(new Request('GET', '/blog/news/' . $slug), 'news', $slug)->body;
        $comment = fn (string $slug, string $status): int => $comments->create([
            'post_id' => (int) $posts->findBySlug($this->blogId, $slug)['id'], 'user_id' => null,
            'author_name' => 'Vera', 'body' => "Remark on $slug", 'status' => $status, 'ip_hash' => 'x',
        ]);

        // Closed and nothing to show: no comments section at all.
        $this->assertStringContainsString('Body of', $page('p1'));
        $this->assertStringNotContainsString('id="comments"', $page('p1'));
        $this->assertStringNotContainsString('Comments are closed', $page('p1'));

        // Closed with a comment: the comment, and the note that there will be no more.
        $comment('p2', 'approved');
        $this->assertStringContainsString('id="comments"', $page('p2'));
        $this->assertStringContainsString('Remark on p2', $page('p2'));
        $this->assertStringContainsString('Comments are closed', $page('p2'));

        // Closed with only a waiting comment: nothing for visitors, the section for an editor.
        $comment('p3', 'pending');
        $this->assertStringNotContainsString('id="comments"', $page('p3'));
        Auth::setUser((new \Angelma\Repos\Users($this->db))->find($this->ownerId));
        $this->assertStringContainsString('Remark on p3', $page('p3'));
        Auth::setUser(null);

        // Open and empty: the section invites the first comment.
        $posts->update((int) $posts->findBySlug($this->blogId, 'p4')['id'], ['comments_mode' => 'anyone']);
        $this->assertStringContainsString('No comments yet.', $page('p4'));
        $this->assertStringContainsString('id="comment-form"', $page('p4'));
    }

    public function testPostPageHasAnEditLinkForEditors(): void
    {
        $id = (int) (new Posts($this->db))->findBySlug($this->blogId, 'p1')['id'];
        $link = '<a class="edit-link" href="/admin/posts/' . $id . '">Edit</a>';
        $page = static fn (): string => (new BlogController())->post(new Request('GET', '/blog/news/p1'), 'news', 'p1')->body;

        $this->assertStringNotContainsString('edit-link', $page());
        Auth::setUser((new \Angelma\Repos\Users($this->db))->find($this->ownerId));
        $this->assertStringContainsString($link, $page());

        (new \Angelma\Repos\Settings($this->db))->set('edit_links', '0');
        App::forgetSettings();
        $this->assertStringNotContainsString('edit-link', $page());
    }

    public function testFeedIsValidRss(): void
    {
        $response = (new BlogController())->feed(new Request('GET', '/blog/news/feed'), 'news');
        $this->assertSame('application/rss+xml; charset=utf-8', $response->header('Content-Type'));

        $xml = simplexml_load_string($response->body);
        $this->assertNotFalse($xml);
        $this->assertSame('2.0', (string) $xml['version']);
        $channel = $xml->channel;
        $this->assertStringStartsWith('News & <Views>', (string) $channel->title);
        $this->assertSame('https://example.com/blog/news', (string) $channel->link);
        $this->assertCount(12, $channel->item);

        $first = $channel->item[0];
        $this->assertSame('Post 12 <&>', (string) $first->title);
        $this->assertSame('https://example.com/blog/news/p12', (string) $first->link);
        $this->assertSame('Mon, 12 Oct 2026 23:30:00 +0000', (string) $first->pubDate);
        $this->assertNotFalse(\DateTimeImmutable::createFromFormat(DATE_RSS, (string) $first->pubDate));
        $this->assertStringContainsString('href="https://example.com/x"', (string) $first->description);
    }
}
