<?php

declare(strict_types=1);

namespace Angelma\Tests;

use Angelma\Repos\Blogs;
use Angelma\Repos\Boxes;
use Angelma\Repos\Comments;
use Angelma\Repos\Images;
use Angelma\Repos\Pages;
use Angelma\Repos\Posts;
use Angelma\Repos\Rows;
use Angelma\Repos\Settings;

final class DbTest extends DbTestCase
{
    public function testMigrateTwiceIsHarmless(): void
    {
        $this->assertSame(0, $this->db->migrate());
        $this->assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM schema_version'));
        $this->assertSame(1, (int) $this->db->value('SELECT version FROM schema_version'));
    }

    public function testPageWithRowAndBoxRoundTrip(): void
    {
        $owner = $this->makeUser();
        $pages = new Pages($this->db);
        $rows = new Rows($this->db);
        $boxes = new Boxes($this->db);

        $pageId = $pages->create(['slug' => 'about', 'name' => 'About', 'title' => 'About us', 'owner_id' => $owner]);
        $rowId = $rows->create($pageId, 'center');
        $boxId = $boxes->create($rowId, 'text', 6, ['markdown' => '# Hello']);

        $page = $pages->findBySlug('about');
        $this->assertSame($pageId, $page['id']);
        $this->assertSame(1, $page['show_in_menu']);
        $this->assertSame('center', $rows->forPage($pageId)[0]['alignment']);
        $box = $boxes->find($boxId);
        $this->assertSame(['markdown' => '# Hello'], $box['content']);
        $this->assertSame($pageId, $box['page_id']);
        $this->assertSame(6, $boxes->widthSum($rowId));
        $this->assertCount(1, $boxes->forPage($pageId)[$rowId]);
    }

    public function testDeletingPageRemovesRowsAndBoxes(): void
    {
        $owner = $this->makeUser();
        $pages = new Pages($this->db);
        $pageId = $pages->create(['slug' => 'x', 'name' => 'X', 'title' => 'X', 'owner_id' => $owner]);
        $rowId = (new Rows($this->db))->create($pageId);
        (new Boxes($this->db))->create($rowId, 'text', 12, []);

        $pages->delete($pageId);
        $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM rows'));
        $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM boxes'));
    }

    public function testRowMoveRenumbers(): void
    {
        $owner = $this->makeUser();
        $pageId = (new Pages($this->db))->create(['slug' => 'x', 'name' => 'X', 'title' => 'X', 'owner_id' => $owner]);
        $rows = new Rows($this->db);
        $a = $rows->create($pageId);
        $b = $rows->create($pageId);
        $c = $rows->create($pageId);
        $rows->move($c, 'up');
        $this->assertSame([$a, $c, $b], array_column($rows->forPage($pageId), 'id'));
        $rows->move($a, 'up');
        $this->assertSame([$a, $c, $b], array_column($rows->forPage($pageId), 'id'));
    }

    public function testMenuMoveRenumbers(): void
    {
        $owner = $this->makeUser();
        $pages = new Pages($this->db);
        $a = $pages->create(['slug' => 'a', 'name' => 'A', 'title' => 'A', 'owner_id' => $owner]);
        $b = $pages->create(['slug' => 'b', 'name' => 'B', 'title' => 'B', 'owner_id' => $owner, 'show_in_menu' => 0]);
        $c = $pages->create(['slug' => 'c', 'name' => 'C', 'title' => 'C', 'owner_id' => $owner]);

        $pages->moveInMenu($c, 'up');
        $this->assertSame([$a, $c, $b], array_column($pages->all(), 'id'));
        $this->assertSame([1, 2, 3], array_column($pages->all(), 'menu_order'));
        $this->assertSame(['a', 'c'], array_column($pages->menu(), 'slug'));

        // Hidden pages keep their place in the order; moving past the ends does nothing.
        $pages->moveInMenu($a, 'down');
        $this->assertSame([$c, $a, $b], array_column($pages->all(), 'id'));
        $pages->moveInMenu($c, 'up');
        $pages->moveInMenu($b, 'down');
        $this->assertSame([$c, $a, $b], array_column($pages->all(), 'id'));
    }

    public function testMenuOrderAppendsNewPagesLast(): void
    {
        $owner = $this->makeUser();
        $pages = new Pages($this->db);
        $pages->create(['slug' => 'a', 'name' => 'A', 'title' => 'A', 'owner_id' => $owner]);
        $pages->create(['slug' => 'b', 'name' => 'B', 'title' => 'B', 'owner_id' => $owner, 'show_in_menu' => 0]);
        $pages->create(['slug' => 'c', 'name' => 'C', 'title' => 'C', 'owner_id' => $owner]);
        $this->assertSame(['a', 'c'], array_column($pages->menu(), 'slug'));
        $this->assertTrue($pages->slugTaken('a'));
        $this->assertFalse($pages->slugTaken('a', (int) $pages->findBySlug('a')['id']));
    }

    public function testSettingsUpsert(): void
    {
        $settings = new Settings($this->db);
        $settings->set('site_title', 'One');
        $settings->set('site_title', 'Two');
        $this->assertSame('Two', $settings->get('site_title'));
        $this->assertSame(['site_title' => 'Two'], $settings->all());
    }

    public function testBlogPostsAndComments(): void
    {
        $owner = $this->makeUser();
        $blogs = new Blogs($this->db);
        $posts = new Posts($this->db);
        $comments = new Comments($this->db);
        $blogId = $blogs->create(['slug' => 'news', 'title' => 'News', 'owner_id' => $owner]);
        $draft = $posts->create(['blog_id' => $blogId, 'slug' => 'd', 'title' => 'D', 'body_markdown' => '',
            'status' => 'draft', 'comments_mode' => 'anyone', 'author_id' => $owner, 'published_at' => null]);
        $pub = $posts->create(['blog_id' => $blogId, 'slug' => 'p', 'title' => 'P', 'body_markdown' => '',
            'status' => 'published', 'comments_mode' => 'anyone', 'author_id' => $owner,
            'published_at' => '2026-10-01T10:00:00Z']);

        $this->assertSame(1, $posts->countPublished($blogId));
        $this->assertSame([$pub], array_column($posts->publishedForBlog($blogId, 10), 'id'));
        $this->assertSame('Admin', $posts->find($pub)['author_name']);
        $this->assertTrue($posts->slugTaken($blogId, 'd'));
        $this->assertSame(2, $blogs->postCount($blogId));

        $comments->create(['post_id' => $pub, 'user_id' => null, 'author_name' => 'Ann', 'body' => 'Hi',
            'status' => 'pending', 'ip_hash' => 'h']);
        $comments->create(['post_id' => $pub, 'user_id' => $owner, 'author_name' => 'Admin', 'body' => 'Yo',
            'status' => 'approved', 'ip_hash' => 'h']);
        $this->assertCount(1, $comments->forPost($pub));
        $this->assertCount(2, $comments->forPost($pub, true));
        $this->assertSame(1, $comments->countPending());
        $this->assertSame(2, $comments->countSince('h', '2000-01-01T00:00:00Z'));

        $posts->delete($pub);
        $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM comments'));
        $this->assertNotNull($posts->find($draft));
    }

    public function testPagesUsingImage(): void
    {
        $owner = $this->makeUser();
        $imageId = (new Images($this->db))->create(['filename' => 'abc.jpg', 'original_name' => 'a.jpg',
            'mime' => 'image/jpeg', 'width' => 10, 'height' => 10, 'size' => 100, 'uploader_id' => $owner]);
        $pageId = (new Pages($this->db))->create(['slug' => 'x', 'name' => 'X', 'title' => 'X', 'owner_id' => $owner]);
        $rowId = (new Rows($this->db))->create($pageId);
        $boxes = new Boxes($this->db);
        $boxes->create($rowId, 'image', 6, ['image_id' => $imageId]);
        $boxes->create($rowId, 'image', 6, ['image_id' => $imageId + 1]);

        $using = $boxes->pagesUsing('image', 'image_id', $imageId);
        $this->assertSame(['x'], array_column($using, 'slug'));
        $this->assertSame([], $boxes->pagesUsing('image', 'image_id', 999));
    }

    public function testImageSearchEscapesWildcards(): void
    {
        $owner = $this->makeUser();
        $images = new Images($this->db);
        foreach (['Summer_beach.jpg', 'summerXbeach.png', 'winter.jpg'] as $i => $name) {
            $images->create(['filename' => "f$i.jpg", 'original_name' => $name, 'mime' => 'image/jpeg',
                'width' => 1, 'height' => 1, 'size' => 1, 'uploader_id' => $owner]);
        }
        $this->assertSame(['Summer_beach.jpg'], array_column($images->all(null, 'r_b'), 'original_name'));
        $this->assertCount(2, $images->all(null, 'summer'));
    }

    public function testCopyToMakesAConsistentCopy(): void
    {
        $dir = sys_get_temp_dir() . '/angelma-copy-' . bin2hex(random_bytes(4));
        mkdir($dir);
        try {
            $db = \Angelma\Db::sqlite($dir . '/source.sqlite');
            $db->migrate();
            (new Settings($db))->set('site_title', 'Copied');
            $db->copyTo($dir . '/copy.sqlite');
            $copy = \Angelma\Db::sqlite($dir . '/copy.sqlite');
            $this->assertSame('Copied', (new Settings($copy))->get('site_title'));
            $this->assertSame('ok', $copy->value('PRAGMA integrity_check'));
        } finally {
            exec('rm -rf ' . escapeshellarg($dir));
        }
    }
}
