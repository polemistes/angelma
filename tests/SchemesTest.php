<?php

declare(strict_types=1);

namespace Angelma\Tests;

use Angelma\Schemes;
use PHPUnit\Framework\TestCase;

final class SchemesTest extends TestCase
{
    public function testFiveBuiltInSchemesWithAllKeys(): void
    {
        $this->assertSame(['paper', 'slate', 'night', 'forest', 'ocean'], Schemes::ids());
        foreach (Schemes::builtIn() as $colors) {
            $this->assertSame(Schemes::KEYS, array_keys($colors));
        }
    }

    public function testCssUsesVariables(): void
    {
        $css = Schemes::css(Schemes::resolve('night'));
        $this->assertStringStartsWith(':root{--outside-bg:#0e1116;', $css);
        $this->assertStringContainsString('--menu-active-text:', $css);
    }

    public function testCustomSchemeIsSanitized(): void
    {
        $custom = json_encode(['page_bg' => '#ABCDEF', 'text' => 'red;}body{display:none', 'link' => '#123']);
        $colors = Schemes::resolve('custom', (string) $custom);
        $this->assertSame('#abcdef', $colors['page_bg']);
        $this->assertSame(Schemes::get('paper')['text'], $colors['text']);
        $this->assertSame('#123', $colors['link']);
        $this->assertStringNotContainsString('display', Schemes::css($colors));
    }

    public function testUnknownSchemeFallsBackToPaper(): void
    {
        $this->assertSame(Schemes::get('paper'), Schemes::resolve('nope'));
    }
}
