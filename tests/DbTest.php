<?php

declare(strict_types=1);

namespace Angelma\Tests;

use Angelma\Db;
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
        $this->assertSame(2, (int) $this->db->value('SELECT version FROM schema_version'));
        $this->assertSame(Db::SCHEMA_VERSION, (int) $this->db->value('SELECT version FROM schema_version'));
    }

    public function testUpgradeAppliesNewerMigrations(): void
    {
        // A database at schema version 1, as installed before 1.2.0.
        $dir = sys_get_temp_dir() . '/angelma-migrations-' . bin2hex(random_bytes(4));
        mkdir($dir);
        copy(dirname(__DIR__) . '/app/migrations/001_initial.sql', $dir . '/001_initial.sql');
        $db = Db::sqlite(':memory:');
        $this->assertSame(1, $db->migrate($dir));
        unlink($dir . '/001_initial.sql');
        rmdir($dir);
        $this->assertSame(1, (int) $db->value('SELECT version FROM schema_version'));

        $db->upgrade();
        $this->assertSame(Db::SCHEMA_VERSION, (int) $db->value('SELECT version FROM schema_version'));
        $this->assertNull($db->value('SELECT parent_box_id FROM rows WHERE 1 = 0'));
        $db->upgrade();
        $this->assertSame(Db::SCHEMA_VERSION, (int) $db->value('SELECT version FROM schema_version'));
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

    public function testRowsInsideContainer(): void
    {
        $owner = $this->makeUser();
        $pageId = (new Pages($this->db))->create(['slug' => 'x', 'name' => 'X', 'title' => 'X', 'owner_id' => $owner]);
        $rows = new Rows($this->db);
        $boxes = new Boxes($this->db);
        $top = $rows->create($pageId);
        $container = $boxes->create($top, 'container', 6, []);
        $inner1 = $rows->create($pageId, 'left', $container);
        $inner2 = $rows->create($pageId, 'center', $container);
        $text = $boxes->create($inner1, 'text', 12, ['markdown' => 'One']);

        // Positions count within the parent; the page's own rows are unaffected.
        $this->assertSame([1, 2], array_column($rows->forBox($container), 'position'));
        $this->assertSame(1, $rows->find($top)['position']);
        $this->assertSame($container, $rows->find($inner1)['parent_box_id']);
        $this->assertNull($rows->find($top)['parent_box_id']);
        $grouped = $rows->forPageByParent($pageId);
        $this->assertSame([$top], array_column($grouped[0], 'id'));
        $this->assertSame([$inner1, $inner2], array_column($grouped[$container], 'id'));
        $this->assertSame(0, $rows->depth($top));
        $this->assertSame(1, $rows->depth($inner1));
        $this->assertSame($pageId, $boxes->find($text)['page_id']);
        $this->assertArrayHasKey($inner1, $boxes->forPage($pageId));

        // Moving stays within the container, and the page's own rows ignore the inner ones.
        $rows->move($inner2, 'up');
        $this->assertSame([$inner2, $inner1], array_column($rows->forBox($container), 'id'));
        $this->assertSame([1, 2], array_column($rows->forBox($container), 'position'));
        $rows->move($top, 'down');
        $this->assertSame([$top], array_column($rows->forPageByParent($pageId)[0], 'id'));
        $this->assertSame([$inner2, $inner1], array_column($rows->forBox($container), 'id'));
    }

    public function testDeletingContainersIsRecursive(): void
    {
        $owner = $this->makeUser();
        $pages = new Pages($this->db);
        $pageId = $pages->create(['slug' => 'x', 'name' => 'X', 'title' => 'X', 'owner_id' => $owner]);
        $rows = new Rows($this->db);
        $boxes = new Boxes($this->db);
        $count = fn (string $table): int => (int) $this->db->value("SELECT COUNT(*) FROM $table");

        // A container two deep: the box delete removes both levels, the sibling survives.
        $top = $rows->create($pageId);
        $outer = $boxes->create($top, 'container', 6, []);
        $sibling = $boxes->create($top, 'text', 6, []);
        $outerRow = $rows->create($pageId, 'left', $outer);
        $inner = $boxes->create($outerRow, 'container', 12, []);
        $innerRow = $rows->create($pageId, 'left', $inner);
        $boxes->create($innerRow, 'text', 12, []);
        $this->assertSame(2, $rows->depth($innerRow));
        $this->assertSame(3, $count('rows'));
        $this->assertSame(4, $count('boxes'));

        $boxes->delete($outer);
        $this->assertSame([$top], array_column($rows->forPage($pageId), 'id'));
        $this->assertSame([$sibling], array_column($boxes->forRow($top), 'id'));

        // Deleting a row removes the containers in it.
        $outer = $boxes->create($top, 'container', 6, []);
        $boxes->create($rows->create($pageId, 'left', $outer), 'text', 12, []);
        $rows->delete($top);
        $this->assertSame(0, $count('rows'));
        $this->assertSame(0, $count('boxes'));

        // And so does deleting the page.
        $top = $rows->create($pageId);
        $outer = $boxes->create($top, 'container', 6, []);
        $boxes->create($rows->create($pageId, 'left', $outer), 'text', 12, []);
        $pages->delete($pageId);
        $this->assertSame(0, $count('rows'));
        $this->assertSame(0, $count('boxes'));
    }

    public function testImageUsageInsideContainerIsFound(): void
    {
        $owner = $this->makeUser();
        $pageId = (new Pages($this->db))->create(['slug' => 'x', 'name' => 'Nested', 'title' => 'X', 'owner_id' => $owner]);
        $rows = new Rows($this->db);
        $boxes = new Boxes($this->db);
        $container = $boxes->create($rows->create($pageId), 'container', 6, []);
        $innerRow = $rows->create($pageId, 'left', $container);
        $boxes->create($innerRow, 'image', 12, ['image_id' => 42, 'alt' => '', 'caption' => '', 'fit' => 'cover']);
        $this->assertSame(['Nested'], array_column($boxes->pagesUsing('image', 'image_id', 42), 'name'));
        $this->assertSame([], $boxes->pagesUsing('image', 'image_id', 43));
    }

    public function testContainerRendersNestedRows(): void
    {
        $owner = $this->makeUser();
        $pages = new Pages($this->db);
        $pageId = $pages->create(['slug' => 'x', 'name' => 'X', 'title' => 'Nested page', 'owner_id' => $owner]);
        $rows = new Rows($this->db);
        $boxes = new Boxes($this->db);
        $top = $rows->create($pageId);
        $boxes->create($top, 'text', 6, ['markdown' => 'Tall'], 400);
        $container = $boxes->create($top, 'container', 6, []);
        $boxes->create($rows->create($pageId, 'left', $container), 'text', 12, ['markdown' => 'Upper']);
        $boxes->create($rows->create($pageId, 'right', $container), 'text', 6, ['markdown' => 'Lower']);
        $rows->create($pageId, 'left', $container); // an empty inner row is not shown

        $html = render('site/page', [
            'page' => $pages->find($pageId),
            'rowsByParent' => $rows->forPageByParent($pageId),
            'boxes' => $boxes->forPage($pageId),
        ]);
        $pattern = '#<div class="row cols-12 align-left">\s*'
            . '<div class="box box-text w-6 fixed" style="--h:400px">.*?Tall.*?</div>\s*'
            . '<div class="box box-container w-6">\s*'
            . '<div class="row cols-12 align-left">\s*<div class="box box-text w-12">.*?Upper.*?</div>\s*</div>\s*'
            . '<div class="row cols-6 align-right">\s*<div class="box box-text w-6">.*?Lower.*?</div>\s*</div>\s*'
            . '</div>\s*</div>#s';
        $this->assertSame(1, preg_match($pattern, $html), $html);
        $this->assertSame(3, substr_count($html, '<div class="row '), $html);
    }

    public function testMoveBoxBetweenRowsAndPages(): void
    {
        $owner = $this->makeUser();
        $pages = new Pages($this->db);
        $rows = new Rows($this->db);
        $boxes = new Boxes($this->db);
        $a = $pages->create(['slug' => 'a', 'name' => 'A', 'title' => 'A', 'owner_id' => $owner]);
        $b = $pages->create(['slug' => 'b', 'name' => 'B', 'title' => 'B', 'owner_id' => $owner]);
        $rowA = $rows->create($a);
        $text = $boxes->create($rowA, 'text', 6, ['markdown' => 'Hi']);
        $container = $boxes->create($rowA, 'container', 6, []);
        $innerRow = $rows->create($a, 'left', $container);
        $innerText = $boxes->create($innerRow, 'text', 12, []);
        $rowB = $rows->create($b);
        $boxes->create($rowB, 'text', 3, []);

        $this->assertTrue($boxes->isInside($innerRow, $container));
        $this->assertFalse($boxes->isInside($rowA, $container));
        $this->assertFalse($boxes->isInside($rowB, $container));
        $this->assertSame(0, $boxes->innerLevels($container));
        $this->assertSame([$text, $container], array_column($boxes->topLevelForPage($a), 'id'));

        // The container moves to page B with a new width; the rows inside follow.
        $boxes->moveTo($container, $rowB, 9);
        $moved = $boxes->find($container);
        $this->assertSame([$rowB, 2, 9, $b], [$moved['row_id'], $moved['position'], $moved['width'], $moved['page_id']]);
        $this->assertSame($b, $rows->find($innerRow)['page_id']);
        $this->assertSame($b, $boxes->find($innerText)['page_id']);
        $this->assertSame([$text], array_column($boxes->forRow($rowA), 'id'));
        $this->assertSame(12, $boxes->widthSum($rowB));

        // A container inside the container counts as one inner level.
        $deep = $boxes->create($innerRow, 'container', 6, []);
        $this->assertSame(1, $boxes->innerLevels($container));
        $this->assertSame(0, $boxes->innerLevels($deep));
        $this->assertTrue($boxes->isInside($rows->create($b, 'left', $deep), $container));
    }

    public function testDeletingContainerKeepingContents(): void
    {
        $owner = $this->makeUser();
        $pages = new Pages($this->db);
        $rows = new Rows($this->db);
        $boxes = new Boxes($this->db);
        $pageId = $pages->create(['slug' => 'x', 'name' => 'X', 'title' => 'X', 'owner_id' => $owner]);
        $workbench = $pages->create(['slug' => 'wb', 'name' => 'W', 'title' => 'W', 'owner_id' => $owner]);
        $top = $rows->create($pageId);
        $container = $boxes->create($top, 'container', 12, []);
        $row1 = $rows->create($pageId, 'left', $container);
        $a = $boxes->create($row1, 'text', 6, ['markdown' => 'A']);
        $z = $boxes->create($row1, 'container', 6, []);
        $b = $boxes->create($rows->create($pageId, 'left', $z), 'text', 12, ['markdown' => 'B']);
        $c = $boxes->create($rows->create($pageId, 'left', $container), 'text', 12, ['markdown' => 'C']);
        $this->assertSame(4, $boxes->countInside($container));
        $this->assertSame(1, $boxes->countInside($z));

        $this->assertSame(3, $boxes->deleteKeepingContents($container, $workbench));
        $this->assertNull($boxes->find($container));
        $this->assertSame([$top], array_column($rows->forPage($pageId), 'id'), 'only the empty top row is left on the page');
        $kept = $boxes->topLevelForPage($workbench);
        $this->assertSame([$a, $z, $c], array_column($kept, 'id'));
        $this->assertSame([6, 6, 12], array_column($kept, 'width'));
        $this->assertSame(3, count($rows->forPageByParent($workbench)[0]), 'one row per kept box');
        $this->assertSame($workbench, $boxes->find($b)['page_id'], 'the nested container moved as a whole');
        $this->assertSame([$b], array_column($boxes->forRow((int) $rows->forBox($z)[0]['id']), 'id'));
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
        $this->assertSame(['Summer_beach.jpg'], array_column($images->all('r_b'), 'original_name'));
        $this->assertCount(2, $images->all('summer'));
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
