<?php

declare(strict_types=1);

namespace Angelma\Tests;

use Angelma\App;
use Angelma\Auth;
use Angelma\Controllers\SiteController;
use Angelma\Repos\Boxes;
use Angelma\Repos\Pages;
use Angelma\Repos\Rows;
use Angelma\Repos\Settings;
use Angelma\Repos\Users;
use Angelma\Request;

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
}
