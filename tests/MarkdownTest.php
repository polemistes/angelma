<?php

declare(strict_types=1);

namespace Angelma\Tests;

use Angelma\Markdown;
use PHPUnit\Framework\TestCase;

final class MarkdownTest extends TestCase
{
    public function testH1BecomesH2AndDeepHeadingsStopAtH4(): void
    {
        $html = Markdown::render("# Big\n\n#### Four\n\n###### Six");
        $this->assertStringContainsString('<h2>Big</h2>', $html);
        $this->assertStringContainsString('<h4>Four</h4>', $html);
        $this->assertStringContainsString('<h4>Six</h4>', $html);
        $this->assertStringNotContainsString('<h1', $html);
    }

    public function testRawHtmlIsStripped(): void
    {
        $html = Markdown::render("Hello <script>alert(1)</script>\n\n<div onclick=\"x()\">block</div>\n\n<iframe src=\"https://evil\"></iframe>");
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<div', $html);
        $this->assertStringNotContainsString('<iframe', $html);
    }

    public function testUnsafeLinksAreRemoved(): void
    {
        $html = Markdown::render('[x](javascript:alert(1)) ![y](data:text/html;base64,AAAA)');
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('data:text/html', $html);
    }

    public function testExternalLinksGetNoopener(): void
    {
        $html = Markdown::render('[ext](https://example.com/a) [int](/about)');
        $this->assertStringContainsString('<a rel="noopener" href="https://example.com/a">ext</a>', $html);
        $this->assertStringContainsString('<a href="/about">int</a>', $html);
    }

    public function testSupportedElements(): void
    {
        $html = Markdown::render("**b** *i* `c`\n\n- a\n- b\n\n1. one\n\n> quote\n\n---\n\n```\ncode\n```\n\n![alt](https://example.com/i.png)");
        foreach (['<strong>b</strong>', '<em>i</em>', '<code>c</code>', '<ul>', '<ol>', '<blockquote>', '<hr />', '<pre><code>code', '<img src="https://example.com/i.png" alt="alt"'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
    }

    public function testFirstParagraph(): void
    {
        $this->assertSame('Intro with link and code.', Markdown::firstParagraph("# Head\n\nIntro *with* [link](/x) and `code`.\n\nSecond."));
        $this->assertSame('', Markdown::firstParagraph("# Only a heading"));
        $this->assertSame('abcd…', Markdown::firstParagraph('abcdefghij', 5));
    }
}
