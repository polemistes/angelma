<?php

declare(strict_types=1);

namespace Angelma\Tests;

use Angelma\App;
use Angelma\Auth;
use Angelma\Controllers\Admin\PagesController;
use Angelma\Repos\Pages;
use Angelma\Repos\Users;
use Angelma\Request;
use Angelma\Updater;

/** Folders of your own next to Angelma's (PLAN.md section 6.18). */
final class OwnFoldersTest extends DbTestCase
{
    private string $root;
    private string $savedRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedRoot = App::$root;
        $this->root = sys_get_temp_dir() . '/angelma-folders-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/mine', 0775, true);
        file_put_contents($this->root . '/notes', 'a file, not a folder');
        symlink($this->savedRoot . '/app', $this->root . '/app'); // the views
        App::$root = $this->root;
    }

    protected function tearDown(): void
    {
        App::$root = $this->savedRoot;
        @unlink($this->root . '/app');
        Updater::removeTree($this->root);
        Auth::reset();
        parent::tearDown();
    }

    public function testAFolderTakesTheAddress(): void
    {
        $this->assertTrue(Pages::isFolder('mine'));
        $this->assertTrue(Pages::reserved('mine'));
        $this->assertFalse(Pages::isFolder('notes'), 'top-level files are not served, so they take nothing');
        $this->assertFalse(Pages::isFolder('about'));
        $this->assertFalse(Pages::isFolder(''), 'the site folder itself is not a slug');
        $this->assertFalse(Pages::isFolder('mine/..'));
        $this->assertTrue(Pages::reserved('admin'));
        $this->assertFalse(Pages::reserved('about'));
    }

    public function testDerivedSlugsGoAroundAFolder(): void
    {
        $this->assertSame('mine-2', slugify('Mine', static fn (string $s): bool => Pages::reserved($s)));
    }

    public function testThePageFormRefusesAFolderAddress(): void
    {
        Auth::setUser((new Users($this->db))->find($this->makeUser('editor', 'editor')));
        $form = ['name' => 'Mine', 'title' => '', 'meta_description' => ''];

        $refused = (new PagesController())->create(new Request('POST', '/admin/pages', [], $form + ['slug' => 'mine']));
        $this->assertSame(422, $refused->status);
        $this->assertStringContainsString('The address &quot;mine&quot; belongs to a folder on the server.', $refused->body);

        (new PagesController())->create(new Request('POST', '/admin/pages', [], $form + ['slug' => '']));
        $this->assertNotNull((new Pages($this->db))->findBySlug('mine-2'), 'a slug from the name goes around the folder');
        $this->assertNull((new Pages($this->db))->findBySlug('mine'));
    }
}
