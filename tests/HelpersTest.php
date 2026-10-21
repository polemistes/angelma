<?php

declare(strict_types=1);

namespace Angelma\Tests;

use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    public function testSlugifyNorwegianLetters(): void
    {
        $this->assertSame('blabaersyltetoy', slugify('Blåbærsyltetøy'));
        $this->assertSame('aerfugl-oye-ase', slugify('Ærfugl Øye Åse'));
    }

    public function testSlugifyPunctuationAndSpaces(): void
    {
        $this->assertSame('hello-world', slugify('  Hello, World!  '));
        $this->assertSame('a-b-c', slugify('a---b___c'));
        $this->assertSame('cafe-creme-2026', slugify('Café Crème 2026'));
        $this->assertSame('', slugify('!!!'));
    }

    public function testSlugifyAppendsNumberOnCollision(): void
    {
        $taken = ['about', 'about-2'];
        $this->assertSame('about-3', slugify('About', fn (string $s): bool => in_array($s, $taken, true)));
        $this->assertSame('contact', slugify('Contact', fn (string $s): bool => in_array($s, $taken, true)));
    }

    public function testEscape(): void
    {
        $this->assertSame('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', e('<script>alert("x")</script>'));
        $this->assertSame('it&apos;s &amp; more', e("it's & more"));
        $this->assertSame('', e(null));
        $this->assertSame('42', e(42));
    }

    public function testTranslationWithPlaceholders(): void
    {
        $this->assertSame('Page not found', t('error_404_title'));
        $this->assertSame('missing_key', t('missing_key'));
    }

    public function testUrl(): void
    {
        $this->assertSame('/', url());
        $this->assertSame('/blog/news', url('blog/news'));
        $this->assertSame('/blog/news?page=2', url('/blog/news', ['page' => 2]));
    }

    public function testSafeReturnPath(): void
    {
        $this->assertSame('/about', safe_return_path('/about'));
        $this->assertSame('/', safe_return_path('//evil.example'));
        $this->assertSame('/', safe_return_path('https://evil.example'));
        $this->assertSame('/', safe_return_path('/\\evil.example'));
    }

    public function testNowIsUtcIso(): void
    {
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', now());
    }
}
