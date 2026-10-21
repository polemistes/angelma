<?php

declare(strict_types=1);

namespace Angelma\Tests;

use Angelma\App;
use Angelma\Db;
use Angelma\Installer;
use PHPUnit\Framework\TestCase;

final class InstallerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/angelma-install-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/data/sessions', 0777, true);
        mkdir($this->dir . '/uploads');
    }

    protected function tearDown(): void
    {
        App::setDb(angelma_test_db());
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function testValidation(): void
    {
        $this->assertSame([], Installer::validateSite(['site_title' => 'Site', 'page_width' => '960', 'scheme' => 'paper']));
        $this->assertArrayHasKey('page_width', Installer::validateSite(['site_title' => 'S', 'page_width' => '2000', 'scheme' => 'paper']));
        $this->assertArrayHasKey('scheme', Installer::validateSite(['site_title' => 'S', 'page_width' => '960', 'scheme' => 'x']));
        $ok = ['username' => 'admin', 'display_name' => 'A', 'password' => '0123456789', 'password_repeat' => '0123456789'];
        $this->assertSame([], Installer::validateAdmin($ok));
        $this->assertArrayHasKey('password', Installer::validateAdmin(['password' => '123456789'] + $ok));
        $this->assertArrayHasKey('password_repeat', Installer::validateAdmin(['password_repeat' => 'other-pass'] + $ok));
        $this->assertArrayHasKey('username', Installer::validateAdmin(['username' => 'a b'] + $ok));
    }

    public function testInstallCreatesEverything(): void
    {
        $installer = new Installer($this->dir);
        $this->assertTrue($installer->requirementsMet());
        $installer->install(
            ['site_title' => 'Test', 'page_width' => '1000', 'scheme' => 'night'],
            ['username' => 'Boss', 'display_name' => 'The Boss', 'password_hash' => password_hash('x', PASSWORD_DEFAULT)],
        );
        $this->assertFileExists($this->dir . '/data/installed.lock');
        $config = require $this->dir . '/data/config.php';
        $this->assertSame(64, strlen($config['secret']));

        $db = Db::sqlite($this->dir . '/data/angelma.sqlite');
        $this->assertSame('boss', $db->value('SELECT username FROM users'));
        $this->assertSame('admin', $db->value('SELECT role FROM users'));
        $home = (string) $db->value("SELECT value FROM settings WHERE key = 'home_page_id'");
        $this->assertSame('home', $db->value('SELECT slug FROM pages WHERE id = ?', [(int) $home]));
        $this->assertSame(12, $db->value('SELECT width FROM boxes'));
        $this->assertSame('night', $db->value("SELECT value FROM settings WHERE key = 'scheme'"));

        // A second run refuses to touch the existing database.
        $this->expectException(\RuntimeException::class);
        $installer->install(
            ['site_title' => 'Again', 'page_width' => '960', 'scheme' => 'paper'],
            ['username' => 'other', 'display_name' => 'O', 'password_hash' => 'x'],
        );
    }
}
