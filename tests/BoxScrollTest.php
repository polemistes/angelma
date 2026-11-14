<?php

declare(strict_types=1);

namespace Angelma\Tests;

use Angelma\App;
use Angelma\Auth;
use Angelma\Controllers\Admin\BoxesController;
use Angelma\Controllers\SiteController;
use Angelma\Repos\Blogs;
use Angelma\Repos\Boxes;
use Angelma\Repos\Pages;
use Angelma\Repos\Rows;
use Angelma\Repos\Settings;
use Angelma\Repos\Users;
use Angelma\Request;

/**
 * Boxes that scroll inside a fixed height (PLAN.md sections 6.2 and 6.13).
 */
final class BoxScrollTest extends DbTestCase
{
    private int $pageId;
    private int $rowId;
    private Boxes $boxes;

    protected function setUp(): void
    {
        parent::setUp();
        $owner = $this->makeUser('owner', 'editor');
        $this->pageId = (new Pages($this->db))->create([
            'slug' => 'home', 'name' => 'Home', 'title' => 'Home', 'meta_description' => '', 'owner_id' => $owner,
        ]);
        (new Settings($this->db))->set('home_page_id', (string) $this->pageId);
        App::forgetSettings();
        $this->rowId = (new Rows($this->db))->create($this->pageId);
        $this->boxes = new Boxes($this->db);
        App::$request = new Request('GET', '/');
        Auth::setUser((new Users($this->db))->find($owner));
    }

    protected function tearDown(): void
    {
        Auth::reset();
        App::$request = null;
        App::forgetSettings();
        parent::tearDown();
    }

    /** The class attribute of a box on the public page, found by its id anchor. */
    private function boxClass(int $id): string
    {
        $html = (new SiteController())->page(new Request('GET', '/'))->body;
        $this->assertSame(1, preg_match('/<div class="([^"]*)" id="box-' . $id . '"/', $html, $m), "box $id");
        return $m[1];
    }

    public function testWhichBoxesScroll(): void
    {
        $box = static fn (string $type, ?int $height, array $content = []): array => ['type' => $type, 'height' => $height, 'content' => $content];
        $this->assertTrue(Boxes::scrolls($box('text', 300, ['scroll' => 1])));
        $this->assertTrue(Boxes::scrolls($box('blog', 300, ['scroll' => 1])));
        $this->assertFalse(Boxes::scrolls($box('text', 300, ['scroll' => 0])));
        $this->assertFalse(Boxes::scrolls($box('blog', 300)), 'boxes saved before 1.4.6 cut off as before');
        $this->assertFalse(Boxes::scrolls($box('text', null, ['scroll' => 1])), 'nothing to scroll without a height');
        $this->assertFalse(Boxes::scrolls($box('image', 300, ['scroll' => 1])));
        $this->assertFalse(Boxes::scrolls($box('embed', 300, ['scroll' => 1])));
        $this->assertTrue(Boxes::scrolls($box('container', 300)), 'a container with a height always scrolls');
        $this->assertFalse(Boxes::scrolls($box('container', null)));
    }

    public function testPublicPageMarksScrollingBoxes(): void
    {
        $scrolling = $this->boxes->create($this->rowId, 'text', 4, ['markdown' => 'Long', 'scroll' => 1], 200);
        $cut = $this->boxes->create($this->rowId, 'text', 4, ['markdown' => 'Long'], 200);
        $free = $this->boxes->create($this->rowId, 'text', 4, ['markdown' => 'Short', 'scroll' => 1]);
        $this->assertSame('box box-text w-4 fixed scroll', $this->boxClass($scrolling));
        $this->assertSame('box box-text w-4 fixed', $this->boxClass($cut));
        $this->assertSame('box box-text w-4', $this->boxClass($free));

        // A container with a height scrolls; it has no id anchor, so look for its class.
        $row = (new Rows($this->db))->create($this->pageId);
        $container = $this->boxes->create($row, 'container', 6, [], 400);
        $this->boxes->create((new Rows($this->db))->create($this->pageId, 'left', $container), 'text', 12, ['markdown' => 'Inside']);
        $html = (new SiteController())->page(new Request('GET', '/'))->body;
        $this->assertStringContainsString('<div class="box box-container w-6 fixed scroll" style="--h:400px">', $html);
    }

    public function testFormSavesScrollAndAContainerHeight(): void
    {
        $controller = new BoxesController();
        $blogId = (new Blogs($this->db))->create(['slug' => 'news', 'title' => 'News', 'owner_id' => Auth::id()]);
        $blog = $this->boxes->create($this->rowId, 'blog', 6, BoxesController::DEFAULT_CONTENT['blog']);
        $post = static fn (int $id, array $fields) => $controller->update(new Request('POST', '/admin/boxes/' . $id, [], $fields), $id);

        $form = $controller->edit(new Request('GET', '/admin/boxes/' . $blog), $blog)->body;
        $this->assertStringContainsString('<input type="checkbox" name="scroll" value="1">', $form);

        $post($blog, ['width' => '6', 'height' => '300', 'blog_id' => (string) $blogId, 'count' => '10', 'text' => 'first', 'scroll' => '1']);
        $this->assertSame(1, $this->boxes->find($blog)['content']['scroll']);
        $this->assertStringContainsString('name="scroll" value="1" checked', $controller->edit(new Request('GET', '/admin/boxes/' . $blog), $blog)->body);
        $post($blog, ['width' => '6', 'height' => '300', 'blog_id' => (string) $blogId, 'count' => '10', 'text' => 'first']);
        $this->assertSame(0, $this->boxes->find($blog)['content']['scroll']);

        // Image boxes have no such choice and store none.
        $image = $this->boxes->create($this->rowId, 'image', 6, BoxesController::DEFAULT_CONTENT['image']);
        $this->assertStringNotContainsString('name="scroll"', $controller->edit(new Request('GET', '/admin/boxes/' . $image), $image)->body);
        $post($image, ['width' => '6', 'height' => '300', 'scroll' => '1']);
        $this->assertArrayNotHasKey('scroll', $this->boxes->find($image)['content']);

        // A container now has the height field, a scroll-free form, and keeps the height.
        $row = (new Rows($this->db))->create($this->pageId);
        $container = $this->boxes->create($row, 'container', 6, []);
        $form = $controller->edit(new Request('GET', '/admin/boxes/' . $container), $container)->body;
        $this->assertStringContainsString('name="height"', $form);
        $this->assertStringNotContainsString('name="scroll"', $form);
        $post($container, ['width' => '6', 'height' => '450']);
        $this->assertSame(450, (int) $this->boxes->find($container)['height']);
        $this->assertSame(422, $post($container, ['width' => '6', 'height' => '10'])->status);
        $post($container, ['width' => '6', 'height' => '']);
        $this->assertNull($this->boxes->find($container)['height']);
    }
}
