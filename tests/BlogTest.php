<?php

declare(strict_types=1);

namespace Angelma\Tests;

use Angelma\App;
use Angelma\Controllers\BlogController;
use Angelma\HttpException;
use Angelma\Repos\Blogs;
use Angelma\Repos\Posts;
use Angelma\Request;

final class BlogTest extends DbTestCase
{
    private int $blogId;

    protected function setUp(): void
    {
        parent::setUp();
        $owner = $this->makeUser('writer', 'editor');
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
