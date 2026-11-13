<?php

declare(strict_types=1);

namespace Angelma\Tests;

use Angelma\App;
use Angelma\Html;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The filter for HTML in text boxes. Every test runs with both parsers: the one of
 * PHP 8.4 and newer, and the older one used on PHP 8.2 and 8.3.
 */
final class HtmlTest extends TestCase
{
    protected function tearDown(): void
    {
        App::$base = '';
    }

    /** @return iterable<string, array{bool}> */
    public static function parsers(): iterable
    {
        if (class_exists(\Dom\HTMLDocument::class)) {
            yield 'newer parser' => [true];
        }
        yield 'older parser' => [false];
    }

    #[DataProvider('parsers')]
    public function testLinkKeepsItsTarget(bool $modern): void
    {
        $this->assertSame(
            '<p>Open <a href="https://example.com/a" target="_blank" rel="noopener">this</a> and <a href="/about" target="_blank">that</a>.</p>',
            Html::clean('<p>Open <a href="https://example.com/a" target="_blank">this</a> and <a href="/about" target="_blank">that</a>.</p>', $modern)
        );
        // Only the usual targets, and rel keeps plain words only.
        $this->assertSame(
            '<a href="https://example.com/" rel="me nofollow noopener">x</a>',
            Html::clean('<a href="https://example.com/" target="javascript:x" rel="ME nofollow x&amp;y">x</a>', $modern)
        );
    }

    #[DataProvider('parsers')]
    public function testScriptsHandlersAndFramesAreRemoved(bool $modern): void
    {
        $this->assertSame('<p>Hello </p>', Html::clean('<p>Hello <script>alert(1)</script></p>', $modern));
        $this->assertSame('<div>block</div>', Html::clean('<div onclick="x()" onmouseover=x()>block</div>', $modern));
        $this->assertSame('', Html::clean('<iframe src="https://evil.example"></iframe><style>p{color:red}</style><!-- note -->', $modern));
        $this->assertSame('<p>ab</p>', Html::clean('<p>a<object data="x">no</object><embed src="x"><video src="x">no</video>b</p>', $modern));
    }

    /** @return iterable<string, array{string}> */
    public static function attacks(): iterable
    {
        yield 'script' => ['<script>alert(1)</script>'];
        yield 'image handler' => ['<img src=x onerror=alert(1)>'];
        yield 'svg' => ['<svg onload=alert(1)><a href="javascript:alert(1)">s</a><script>alert(1)</script></svg>'];
        yield 'math' => ['<math><mtext><table><mglyph><style><img src onerror=alert(1)>'];
        yield 'noscript' => ['<noscript><p title="</noscript><img src=x onerror=alert(1)>">'];
        yield 'attribute' => ['<p title="</p><script>alert(1)</script>" class="\'><script>alert(1)</script>">x</p>'];
        yield 'comment' => ['<!--><script>alert(1)</script>--><p>x</p><!-- --!><img src=x onerror=alert(1)>'];
        yield 'textarea' => ['<textarea><p title="</textarea><img src=x onerror=alert(1)>"></textarea>'];
        yield 'style element' => ['<style><a title="</style><img src=x onerror=alert(1)>"></style>'];
        yield 'template' => ['<template><script>alert(1)</script></template><form><input onfocus=alert(1) autofocus>'];
        yield 'entities' => ['<a href="&#106;avascript&colon;alert(1)">x</a><a href="jav&#x0A;ascript:alert(1)">y</a>'];
        yield 'base and meta' => ['<base href="https://evil.example/"><meta http-equiv="refresh" content="0;url=https://evil.example/"><link rel=stylesheet href=//evil.example/x.css>'];
        yield 'unclosed' => ['<a href="javascript:alert(1)" <p onclick=alert(1)>x<img src="x" onerror="alert(1)"'];
        yield 'style attribute' => ['<p style="background:url(javascript:alert(1));width:expression(alert(1));behavior:url(x.htc)">x</p>'];
    }

    /**
     * Whatever goes in, what comes out holds only allowed elements and attributes and no
     * address that runs a script, also when a browser reads it once more.
     */
    #[DataProvider('attacks')]
    public function testAttacksLeaveNothingThatRuns(string $attack): void
    {
        foreach (self::parsers() as [$modern]) {
            $clean = Html::clean($attack, $modern);
            $this->assertSame($clean, Html::clean($clean, $modern), 'reading the result again changes nothing');
            $document = new \DOMDocument();
            $previous = libxml_use_internal_errors(true);
            $document->loadHTML('<html><body>' . $clean . '</body></html>');
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            foreach ($document->getElementsByTagName('*') as $element) {
                $this->assertNotContains($element->nodeName, [
                    'script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form', 'input', 'textarea',
                    'template', 'noscript', 'base', 'meta', 'link',
                ], $clean);
                foreach ($element->attributes as $attribute) {
                    $this->assertStringStartsNotWith('on', strtolower($attribute->name), $clean);
                    $this->assertDoesNotMatchRegularExpression('/^\s*(javascript|vbscript|data):/i', $attribute->value, $clean);
                    $this->assertDoesNotMatchRegularExpression('/url|expression|behavior/i', $attribute->name === 'style' ? $attribute->value : '', $clean);
                }
            }
        }
    }

    #[DataProvider('parsers')]
    public function testUnsafeAddressesAreRemoved(bool $modern): void
    {
        foreach (['javascript:alert(1)', ' JaVaScRiPt:alert(1)', "jav\tascript:alert(1)", '&#1;javascript:alert(1)', 'vbscript:x', 'data:text/html,x', 'file:///etc/passwd'] as $url) {
            $this->assertSame('<a>x</a>', Html::clean('<a href="' . $url . '">x</a>', $modern), $url);
        }
        foreach (['https://example.com/', 'http://example.com/', 'mailto:someone@example.com', 'tel:+4712345678'] as $url) {
            $this->assertStringContainsString('href="' . $url . '"', Html::clean('<a href="' . $url . '">x</a>', $modern));
        }
        foreach (['/about', 'page.html', '#part', '?page=2', '../up'] as $url) {
            $this->assertSame('<a href="' . $url . '">x</a>', Html::clean('<a href="' . $url . '">x</a>', $modern));
        }
    }

    #[DataProvider('parsers')]
    public function testImages(bool $modern): void
    {
        $this->assertSame(
            '<img src="/uploads/a.jpg" alt="A" width="200" style="float: right">',
            Html::clean('<img src="/uploads/a.jpg" alt="A" width="200" style="float:right" onerror="x()" srcset="x 2x">', $modern)
        );
        $this->assertSame('<img src="data:image/png;base64,AAAA">', Html::clean('<img src="data:image/png;base64,AAAA">', $modern));
        // An image without a usable address is left out.
        $this->assertSame('', Html::clean('<img src="javascript:alert(1)"><img src="data:text/html,x"><img alt="none">', $modern));
    }

    #[DataProvider('parsers')]
    public function testUnknownElementsKeepTheirText(bool $modern): void
    {
        $this->assertSame('<p>red go</p>', Html::clean('<p><font color="red">red</font> <button>go</button></p>', $modern));
        $this->assertSame('<p>word</p>', Html::clean('<p><o:p>word</o:p></p>', $modern));
    }

    #[DataProvider('parsers')]
    public function testEveryElementIsClosedAndExtraEndTagsVanish(bool $modern): void
    {
        $this->assertSame('<div><b>open</b></div>', Html::clean('<div><b>open', $modern));
        $this->assertSame('<p>text</p>after', Html::clean('<p>text</p></div></div></section>after', $modern));
        $this->assertSame('<ul><li>one</li><li>two</li></ul>', Html::clean('<ul><li>one<li>two</ul>', $modern));
        $this->assertSame('<p>one</p><p>two</p>', Html::clean('<p>one<p>two', $modern));
    }

    #[DataProvider('parsers')]
    public function testListItemsAndCellsOnlyInTheirPlace(bool $modern): void
    {
        // A list item inside a list item, or outside a list, would let a browser close
        // elements of the page around the box.
        $clean = Html::clean('<li><div><li>x</li></div></li></div>after', $modern);
        $this->assertStringNotContainsString('<li', $clean);
        $this->assertStringContainsString('x', $clean);
        $this->assertStringContainsString('after', $clean);
        $this->assertSame('<div>cell</div>', Html::clean('<div><td>cell</td></div>', $modern));

        $table = Html::clean('<table><tr><th colspan="2" scope="col">h</th></tr><tr><td>a</td><td rowspan="2">b</td></tr></table>', $modern);
        $this->assertStringContainsString('<tr><th colspan="2" scope="col">h</th></tr><tr><td>a</td><td rowspan="2">b</td></tr>', $table);
        $this->assertStringStartsWith('<table>', $table);
        $this->assertStringEndsWith('</table>', $table);
    }

    #[DataProvider('parsers')]
    public function testStyleKeepsThePlainProperties(bool $modern): void
    {
        $this->assertSame(
            '<div style="text-align: center; color: burlywood; width: calc(100% - 2em); border-left: 2px solid #ccc">x</div>',
            Html::clean('<div style="text-align:center; position:fixed; background:url(//x/y.png); COLOR: burlywood; width: calc(100% - 2em); z-index: 9; border-left: 2px solid #ccc; margin: url(x)">x</div>', $modern)
        );
        $this->assertSame('<span>x</span>', Html::clean('<span style="position:absolute; top:0">x</span>', $modern));
    }

    #[DataProvider('parsers')]
    public function testHeadingsStayBetweenH2AndH4(bool $modern): void
    {
        $this->assertSame('<h2>a</h2><h3>b</h3><h4>c</h4><h4>d</h4>', Html::clean('<h1>a</h1><h3>b</h3><h5>c</h5><h6>d</h6>', $modern));
    }

    #[DataProvider('parsers')]
    public function testCharactersAndEntitiesSurvive(bool $modern): void
    {
        $this->assertSame(
            '<p title="blåbær &amp; &quot;fløte&quot;">Blåbærsyltetøy 😀 &amp; 5 &lt; 6 © –</p>',
            Html::clean('<p title="blåbær &amp; &quot;fløte&quot;">Blåbærsyltetøy 😀 &amp; 5 &lt; 6 &copy; &ndash;</p>', $modern)
        );
        $this->assertSame("<pre><code>a &lt;b&gt;\n  c</code></pre>", Html::clean("<pre><code>a &lt;b&gt;\n  c</code></pre>", $modern));
    }

    #[DataProvider('parsers')]
    public function testSiteAddressesGetTheSubfolder(bool $modern): void
    {
        App::$base = '/site';
        $this->assertSame(
            '<a href="/site/about">a</a><img src="/site/uploads/a.jpg"><a href="//cdn.example.com/x" rel="noopener">b</a><a href="page">c</a>',
            Html::clean('<a href="/about">a</a><img src="/uploads/a.jpg"><a href="//cdn.example.com/x">b</a><a href="page">c</a>', $modern)
        );
    }

    public function testEmptyInput(): void
    {
        $this->assertSame('', Html::clean(''));
        $this->assertSame('', Html::clean("  \n"));
    }
}
