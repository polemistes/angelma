<?php

declare(strict_types=1);

namespace Angelma\Tests;

use Angelma\Embed;
use PHPUnit\Framework\TestCase;

final class EmbedTest extends TestCase
{
    public function testYouTube(): void
    {
        $src = 'https://www.youtube.com/embed/dQw4w9WgXcQ';
        $this->assertSame($src, Embed::parse('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10')['src']);
        $this->assertSame($src, Embed::parse('https://youtu.be/dQw4w9WgXcQ')['src']);
        $this->assertSame($src, Embed::parse('https://m.youtube.com/watch?v=dQw4w9WgXcQ')['src']);
        $this->assertNull(Embed::parse('https://www.youtube.com/watch?v=bad"id'));
    }

    public function testVimeo(): void
    {
        $this->assertSame('https://player.vimeo.com/video/76979871', Embed::parse('https://vimeo.com/76979871')['src']);
        $this->assertSame('https://player.vimeo.com/video/123', Embed::parse('https://player.vimeo.com/video/123?h=abc')['src']);
    }

    public function testMaps(): void
    {
        $google = Embed::parse('https://www.google.com/maps/embed?pb=!1m18!1m12');
        $this->assertSame('google_maps', $google['provider']);
        $this->assertSame('map', $google['ratio']);
        $osm = Embed::parse('https://www.openstreetmap.org/export/embed.html?bbox=10.7,59.9,10.8,59.95&layer=mapnik');
        $this->assertStringStartsWith('https://www.openstreetmap.org/export/embed.html?bbox=', $osm['src']);
    }

    public function testUnknownAndUnsafe(): void
    {
        $this->assertNull(Embed::parse('https://example.com/video'));
        $this->assertNull(Embed::parse('https://www.google.com/maps/place/Oslo'));
        $this->assertNull(Embed::parse('javascript:alert(1)'));
        $this->assertTrue(Embed::isHttpUrl('https://example.com/x'));
        $this->assertFalse(Embed::isHttpUrl('javascript:alert(1)'));
        $this->assertFalse(Embed::isHttpUrl('ftp://example.com'));
    }
}
