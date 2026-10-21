<?php

declare(strict_types=1);

namespace Angelma\Tests;

use Angelma\App;
use Angelma\Markdown;
use Angelma\Request;
use PHPUnit\Framework\TestCase;

final class BasePathTest extends TestCase
{
    protected function tearDown(): void
    {
        App::$base = '';
        $_SERVER['REQUEST_URI'] = '/';
    }

    public function testDetectBaseFromScriptName(): void
    {
        $this->assertSame('', App::detectBase(null, '/index.php'));
        $this->assertSame('', App::detectBase(null, '/install/index.php'));
        $this->assertSame('/site', App::detectBase(null, '/site/index.php'));
        $this->assertSame('/site', App::detectBase(null, '/site/install/index.php'));
        $this->assertSame('/a/b', App::detectBase(null, '/a/b/index.php'));
        $this->assertSame('', App::detectBase(null, ''));
        $this->assertSame('/forced', App::detectBase('forced/', '/other/index.php'));
        $this->assertSame('', App::detectBase('/', '/other/index.php'));
    }

    public function testUrlsGetTheSubfolder(): void
    {
        App::$base = '/site';
        $this->assertSame('/site/', url());
        $this->assertSame('/site/admin/pages', url('/admin/pages'));
        $this->assertSame('/site/login?return=%2Fabout', url('/login', ['return' => '/about']));
        $this->assertSame('/site/uploads/abc123abc123-s.jpg', image_url('abc123abc123.jpg', 's'));
        $this->assertSame('/site/about', redirect('/about')->header('Location'));
    }

    public function testRequestPathIsInsideTheSite(): void
    {
        App::$base = '/site';
        $_SERVER['REQUEST_URI'] = '/site/blog/news?page=2';
        $this->assertSame('/blog/news', Request::fromGlobals()->path);
        $_SERVER['REQUEST_URI'] = '/site';
        $this->assertSame('/', Request::fromGlobals()->path);
        $_SERVER['REQUEST_URI'] = '/sitemap';
        $this->assertSame('/sitemap', Request::fromGlobals()->path);
    }

    public function testMarkdownSiteLinksGetTheSubfolder(): void
    {
        App::$base = '/site';
        $html = Markdown::render('[About](/about) ![Pic](/uploads/a.jpg) [Ext](https://example.com) [Proto](//cdn.example.com/x) [Rel](page)');
        $this->assertStringContainsString('href="/site/about"', $html);
        $this->assertStringContainsString('src="/site/uploads/a.jpg"', $html);
        $this->assertStringContainsString('href="https://example.com"', $html);
        $this->assertStringContainsString('href="//cdn.example.com/x"', $html);
        $this->assertStringContainsString('href="page"', $html);

        App::$base = '';
        $this->assertStringContainsString('href="/about"', Markdown::render('[About](/about)'));
    }
}
