<?php

declare(strict_types=1);

namespace Angelma\Tests;

use Angelma\App;
use Angelma\Auth;
use Angelma\Controllers\Admin\BoxesController;
use Angelma\Controllers\SiteController;
use Angelma\Repos\Boxes;
use Angelma\Repos\Pages;
use Angelma\Repos\Rows;
use Angelma\Repos\Settings;
use Angelma\Repos\Users;
use Angelma\Request;
use Angelma\Response;

/**
 * What logged-in users get on the public site: the link to the admin area and the edit
 * links (PLAN.md section 6.17).
 */
final class SiteLinksTest extends DbTestCase
{
    private int $pageId;
    private int $rowId;
    private int $textBox;

    protected function setUp(): void
    {
        parent::setUp();
        $owner = $this->makeUser('owner', 'admin');
        $this->pageId = (new Pages($this->db))->create([
            'slug' => 'home', 'name' => 'Home', 'title' => 'Home', 'meta_description' => '', 'owner_id' => $owner,
        ]);
        (new Settings($this->db))->set('home_page_id', (string) $this->pageId);
        App::forgetSettings();
        $this->rowId = (new Rows($this->db))->create($this->pageId);
        $this->textBox = (new Boxes($this->db))->create($this->rowId, 'text', 6, ['markdown' => 'Hello there']);
        App::$request = new Request('GET', '/');
        Auth::setUser(null);
    }

    protected function tearDown(): void
    {
        Auth::reset();
        App::$request = null;
        App::forgetSettings();
        parent::tearDown();
    }

    private function actAs(string $role): void
    {
        $id = $this->makeUser($role . '-' . uniqid(), $role);
        Auth::setUser((new Users($this->db))->find($id));
    }

    private function home(): string
    {
        return (new SiteController())->page(new Request('GET', '/'))->body;
    }

    public function testMenuLinksToTheAdminArea(): void
    {
        $this->assertStringNotContainsString('menu-admin', $this->home());

        foreach (['editor', 'admin'] as $role) {
            $this->actAs($role);
            $this->assertStringContainsString('<a class="menu-admin" href="/admin">Admin</a>', $this->home(), $role);
        }

        // A user's only admin screen is their account.
        $this->actAs('user');
        $this->assertStringContainsString('<a class="menu-admin" href="/admin/account">Account</a>', $this->home());
    }

    public function testEditLinksForEditorsAndAdministratorsOnly(): void
    {
        $id = $this->textBox;
        $link = '<a class="edit-link" href="/admin/boxes/' . $id . '?return=%2F%23box-' . $id . '">Edit</a>';

        // Visitors and users get the page as it was: no links, no anchors.
        $this->assertStringNotContainsString('edit-link', $this->home());
        $this->assertStringNotContainsString('id="box-', $this->home());
        $this->actAs('user');
        $this->assertStringNotContainsString('edit-link', $this->home());
        $this->assertStringNotContainsString('id="box-', $this->home());

        foreach (['editor', 'admin'] as $role) {
            $this->actAs($role);
            $this->assertStringContainsString($link, $this->home(), $role);
            $this->assertStringContainsString(' id="box-' . $id . '"', $this->home(), $role);
        }

        // A container has no link of its own; the box inside it has.
        $boxes = new Boxes($this->db);
        $container = $boxes->create($this->rowId, 'container', 6, []);
        $inner = $boxes->create((new Rows($this->db))->create($this->pageId, 'left', $container), 'text', 12, ['markdown' => 'Inside']);
        $page = $this->home();
        $this->assertSame(2, substr_count($page, 'class="edit-link"'));
        $this->assertStringContainsString('/admin/boxes/' . $inner . '?return=', $page);
        $this->assertStringNotContainsString('/admin/boxes/' . $container . '?', $page);
        $this->assertStringNotContainsString('id="box-' . $container . '"', $page);

        // The setting turns the edit links off, not the admin link.
        (new Settings($this->db))->set('edit_links', '0');
        App::forgetSettings();
        $page = $this->home();
        $this->assertStringNotContainsString('edit-link', $page);
        $this->assertStringNotContainsString('id="box-', $page);
        $this->assertStringContainsString('class="menu-admin"', $page);
    }

    public function testBoxFormReturnsToTheSitePageItWasOpenedFrom(): void
    {
        $this->actAs('editor');
        $id = $this->textBox;
        $return = '/#box-' . $id;
        $layout = '/admin/pages/' . $this->pageId . '#row-' . $this->rowId;
        $controller = new BoxesController();
        $save = static fn (array $fields): Response => $controller->update(
            new Request('POST', '/admin/boxes/' . $id, [], $fields + ['width' => '6', 'height' => '', 'markdown' => 'Changed']),
            $id,
        );

        // Opened from the site: the form carries the address and "Cancel" leads there.
        $form = $controller->edit(new Request('GET', '/admin/boxes/' . $id, ['return' => $return]), $id)->body;
        $this->assertStringContainsString('<input type="hidden" name="return" value="' . $return . '">', $form);
        $this->assertStringContainsString('<a class="button secondary" href="' . $return . '">Cancel</a>', $form);

        // "Save and close" goes back to the page, "Save" stays and keeps the address.
        $this->assertSame($return, $save(['return' => $return, 'close' => '1'])->header('Location'));
        $this->assertSame('/admin/boxes/' . $id . '?return=%2F%23box-' . $id, $save(['return' => $return])->header('Location'));
        $this->assertSame('Changed', (new Boxes($this->db))->find($id)['content']['markdown']);

        // A refused value shows the form again, still with the address.
        $refused = $save(['return' => $return, 'close' => '1', 'height' => '5']);
        $this->assertSame(422, $refused->status);
        $this->assertStringContainsString('<input type="hidden" name="return" value="' . $return . '">', $refused->body);

        // Opened from the layout editor: as before.
        $form = $controller->edit(new Request('GET', '/admin/boxes/' . $id), $id)->body;
        $this->assertStringNotContainsString('name="return"', $form);
        $this->assertStringContainsString('href="' . $layout . '">Cancel</a>', $form);
        $this->assertSame($layout, $save(['close' => '1'])->header('Location'));
        $this->assertSame('/admin/boxes/' . $id, $save([])->header('Location'));

        // Only addresses on this site are followed.
        foreach (['//evil.example/x', 'https://evil.example/', '/\\evil.example', 'javascript:alert(1)'] as $outside) {
            $this->assertSame($layout, $save(['return' => $outside, 'close' => '1'])->header('Location'), $outside);
            $form = $controller->edit(new Request('GET', '/admin/boxes/' . $id, ['return' => $outside]), $id)->body;
            $this->assertStringNotContainsString('evil', $form, $outside);
            $this->assertStringNotContainsString('javascript:alert', $form, $outside);
        }
    }
}
