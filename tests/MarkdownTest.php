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

    public function testTextBoxesKeepHarmlessHtml(): void
    {
        $html = Markdown::render("# Big\n\nA [md](https://example.com/a) link and <a href=\"/about\" target=\"_blank\">one in a new tab</a>.<script>alert(1)</script>\n\n<div onclick=\"x()\" style=\"text-align:center\">\n\n**bold** inside\n\n</div>", true);
        $this->assertStringContainsString('<h2>Big</h2>', $html);
        $this->assertStringContainsString('<a href="https://example.com/a" rel="noopener">md</a>', $html);
        $this->assertStringContainsString('<a href="/about" target="_blank">one in a new tab</a>', $html);
        $this->assertStringContainsString('<div style="text-align: center">', $html);
        $this->assertStringContainsString('<strong>bold</strong> inside', $html);
        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        // Everything Markdown itself makes comes through the filter unchanged in meaning.
        $html = Markdown::render("**b** *i* `c`\n\n- a\n- b\n\n1. one\n\n> quote\n\n---\n\n```\ncode <b>\n```\n\n![alt](https://example.com/i.png)", true);
        foreach (['<strong>b</strong>', '<em>i</em>', '<code>c</code>', "<ul>\n<li>a</li>", "<ol>\n<li>one</li>", '<blockquote>', '<hr>', '<pre><code>code &lt;b&gt;', '<img src="https://example.com/i.png" alt="alt">'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
        // Only where it is asked for: every other text still has its HTML stripped.
        $this->assertStringNotContainsString('target', Markdown::render('<a href="/about" target="_blank">x</a>'));
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
