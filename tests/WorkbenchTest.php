<?php

declare(strict_types=1);

namespace Angelma\Tests;

use Angelma\App;
use Angelma\Repos\Pages;
use Angelma\Workbench;

final class WorkbenchTest extends DbTestCase
{
    public function testCreatedOnceAndLeftOutOfLists(): void
    {
        $owner = $this->makeUser();
        $pages = new Pages($this->db);
        $pages->create(['slug' => 'about', 'name' => 'About', 'title' => 'About', 'owner_id' => $owner]);
        $this->assertSame(0, Workbench::id());
        $this->assertFalse(Workbench::is(1));

        $workbench = Workbench::page();
        $id = (int) $workbench['id'];
        $this->assertSame('workbench', $workbench['slug']);
        $this->assertSame(0, $workbench['show_in_menu']);
        $this->assertSame($id, Workbench::id());
        $this->assertSame($id, (int) App::setting(Workbench::SETTING));
        $this->assertTrue(Workbench::is($id));
        $this->assertSame($id, (int) Workbench::page()['id'], 'a second call returns the same page');
        $this->assertSame(2, $pages->count());

        $this->assertSame(['about'], array_column($pages->all(), 'slug'));
        $this->assertSame(['about'], array_column($pages->menu(), 'slug'));
        $this->assertNotNull($pages->find($id));
        $this->assertNotNull($pages->findBySlug('workbench'));
    }

    public function testSlugAvoidsAnExistingPage(): void
    {
        $owner = $this->makeUser();
        (new Pages($this->db))->create(['slug' => 'workbench', 'name' => 'W', 'title' => 'W', 'owner_id' => $owner]);
        $this->assertSame('workbench-2', Workbench::page()['slug']);
    }
}
