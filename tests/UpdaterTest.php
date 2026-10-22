<?php

declare(strict_types=1);

namespace Angelma\Tests;

use Angelma\Updater;
use PHPUnit\Framework\TestCase;

final class UpdaterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/angelma-updater-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        Updater::removeTree($this->dir);
    }

    /** @param array<string, string> $files */
    private function tree(string $base, array $files): string
    {
        foreach ($files as $path => $content) {
            if (!is_dir(dirname("$base/$path"))) {
                mkdir(dirname("$base/$path"), 0775, true);
            }
            file_put_contents("$base/$path", $content);
        }
        return $base;
    }

    /** A fake installed site at version 1.3.0, without an install folder. */
    private function site(): string
    {
        return $this->tree($this->dir . '/site', [
            'index.php' => 'old index',
            '.htaccess' => 'old rules',
            'robots.txt' => 'old robots',
            'app/version.php' => "<?php return '1.3.0';",
            'app/Old.php' => 'stale',
            'app/Views/site/page.php' => 'old view',
            'vendor/autoload.php' => 'old autoload',
            'vendor/old/lib.php' => 'stale lib',
            'assets/site.css' => 'old css',
            'data/config.php' => 'secret',
            'data/sessions/.gitkeep' => '',
            'uploads/photo.jpg' => 'jpeg',
        ]);
    }

    /** A fake release tree. */
    private function release(string $version): string
    {
        return $this->tree($this->dir . '/release', [
            'index.php' => 'new index',
            '.htaccess' => 'new rules',
            'robots.txt' => 'new robots',
            'CHANGELOG.md' => '# log',
            'composer.json' => '{"require":{"php":">=8.2"}}',
            'app/version.php' => "<?php return '$version';",
            'app/New.php' => 'fresh',
            'app/Views/site/page.php' => 'new view',
            'vendor/autoload.php' => 'new autoload',
            'vendor/new/lib.php' => 'fresh lib',
            'assets/site.css' => 'new css',
            'install/index.php' => 'installer',
            'data/.htaccess' => 'deny',
            'uploads/.htaccess' => 'deny',
        ]);
    }

    private function zipOf(string $tree, string $zip): void
    {
        $archive = new \ZipArchive();
        $archive->open($zip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tree, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $path => $info) {
            if ($info->isFile()) {
                $archive->addFile((string) $path, substr((string) $path, strlen($tree) + 1));
            }
        }
        $archive->close();
    }

    public function testParseRelease(): void
    {
        $updater = new Updater($this->site(), 'polemistes/angelma');
        $json = [
            'tag_name' => 'v9.0.0',
            'body' => 'Notes',
            'html_url' => 'https://github.com/polemistes/angelma/releases/tag/v9.0.0',
            'assets' => [
                ['name' => 'angelma-9.0.0.zip', 'browser_download_url' => 'https://github.com/polemistes/angelma/releases/download/v9.0.0/angelma-9.0.0.zip'],
                ['name' => 'angelma-9.0.0.zip.sha256', 'browser_download_url' => 'https://evil.example/angelma-9.0.0.zip.sha256'],
            ],
        ];
        $release = $updater->parseRelease($json);
        $this->assertSame('9.0.0', $release['version']);
        $this->assertTrue($release['newer']);
        $this->assertSame('Notes', $release['notes']);
        $this->assertStringStartsWith('https://github.com/polemistes/angelma/releases/download/', (string) $release['zip_url']);
        $this->assertNull($release['sum_url'], 'an asset from another host is ignored');

        $this->assertFalse($updater->parseRelease(['tag_name' => 'v1.3.0'])['newer']);
        $this->assertFalse($updater->parseRelease(['tag_name' => 'v1.2.9'])['newer']);
        $this->expectException(\RuntimeException::class);
        $updater->parseRelease(['tag_name' => 'nightly']);
    }

    public function testChecksum(): void
    {
        $file = $this->dir . '/x.zip';
        file_put_contents($file, 'payload');
        $hash = hash('sha256', 'payload');
        Updater::verifyChecksum($file, "$hash  angelma-9.0.0.zip\n");
        Updater::verifyChecksum($file, strtoupper($hash));
        try {
            Updater::verifyChecksum($file, str_repeat('0', 64));
            $this->fail('A wrong digest must be refused');
        } catch (\RuntimeException $e) {
            $this->assertSame(t('update_error_checksum'), $e->getMessage());
        }
        $this->expectException(\RuntimeException::class);
        Updater::verifyChecksum($file, 'not a checksum');
    }

    public function testExtractRefusesPathsOutsideTheFolder(): void
    {
        $zip = $this->dir . '/bad.zip';
        $archive = new \ZipArchive();
        $archive->open($zip, \ZipArchive::CREATE);
        $archive->addFromString('ok.txt', 'fine');
        $archive->addFromString('../evil.txt', 'escaped');
        $archive->close();
        try {
            Updater::extract($zip, $this->dir . '/out');
            $this->fail('An entry with .. must be refused');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('../evil.txt', $e->getMessage());
        }
        $this->assertFileDoesNotExist($this->dir . '/evil.txt');
    }

    public function testApplyReplacesCodeAndKeepsData(): void
    {
        $site = $this->site();
        $release = $this->release('9.0.0');
        $summary = (new Updater($site))->apply($release);

        $this->assertSame('new index', file_get_contents("$site/index.php"));
        $this->assertSame('new rules', file_get_contents("$site/.htaccess"));
        $this->assertSame('9.0.0', (new Updater($site))->currentVersion());
        $this->assertSame('fresh', file_get_contents("$site/app/New.php"));
        $this->assertSame('new view', file_get_contents("$site/app/Views/site/page.php"));
        $this->assertFileDoesNotExist("$site/app/Old.php");
        $this->assertFileDoesNotExist("$site/vendor/old/lib.php");
        $this->assertDirectoryDoesNotExist("$site/vendor/old");
        $this->assertSame('fresh lib', file_get_contents("$site/vendor/new/lib.php"));
        $this->assertSame('new css', file_get_contents("$site/assets/site.css"));
        $this->assertSame('secret', file_get_contents("$site/data/config.php"), 'data is never touched');
        $this->assertFileDoesNotExist("$site/data/.htaccess");
        $this->assertSame('jpeg', file_get_contents("$site/uploads/photo.jpg"));
        $this->assertDirectoryDoesNotExist("$site/install", 'a deleted installer stays deleted');
        $this->assertFileDoesNotExist("$site/data/updating.lock");
        $this->assertSame(2, $summary['removed']);
        $this->assertGreaterThan(8, $summary['copied']);
    }

    public function testInstallFromZipAndRollback(): void
    {
        $site = $this->site();
        $release = $this->release('9.0.0');
        $zip = $this->dir . '/angelma-9.0.0.zip';
        $this->zipOf($release, $zip);
        $checksum = hash_file('sha256', $zip) . "  angelma-9.0.0.zip\n";
        $updater = new Updater($site);

        try {
            $updater->installFromZip($zip, $checksum, '8.0.0');
            $this->fail('A version mismatch must stop the install');
        } catch (\RuntimeException $e) {
            $this->assertSame('old index', file_get_contents("$site/index.php"), 'nothing replaced');
        }

        $summary = $updater->installFromZip($zip, $checksum, '9.0.0');
        $this->assertSame('9.0.0', $summary['version']);
        $this->assertSame('9.0.0', $updater->currentVersion());
        $this->assertSame('new index', file_get_contents("$site/index.php"));
        $this->assertFileDoesNotExist("$site/app/Old.php");
        $this->assertSame('secret', file_get_contents("$site/data/config.php"));
        $this->assertSame('1.3.0', $updater->previousVersion());
        $this->assertDirectoryDoesNotExist("$site/data/updates/unpacked");

        $restored = $updater->rollback();
        $this->assertSame('1.3.0', $restored['version']);
        $this->assertSame('old index', file_get_contents("$site/index.php"));
        $this->assertSame('stale', file_get_contents("$site/app/Old.php"));
        $this->assertFileDoesNotExist("$site/app/New.php");
        $this->assertSame('old css', file_get_contents("$site/assets/site.css"));
        $this->assertSame('secret', file_get_contents("$site/data/config.php"));
    }
}
