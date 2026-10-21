<?php

declare(strict_types=1);

namespace Angelma;

/**
 * Colour schemes: the built-in ones in app/schemes/*.json and the custom one stored in
 * the custom_scheme setting.
 */
final class Schemes
{
    public const KEYS = [
        'outside_bg', 'page_bg', 'text', 'heading', 'link', 'link_hover', 'menu_bg', 'menu_text',
        'menu_active_bg', 'menu_active_text', 'box_bg', 'box_border', 'caption', 'footer_bg', 'footer_text',
    ];

    public const DEFAULT = 'paper';
    public const ORDER = ['paper', 'slate', 'night', 'forest', 'ocean'];

    /** @var array<string, array<string, string>>|null */
    private static ?array $builtIn = null;

    /** @return array<string, array<string, string>> scheme id => colours, in display order */
    public static function builtIn(): array
    {
        if (self::$builtIn === null) {
            self::$builtIn = [];
            $files = glob(__DIR__ . '/schemes/*.json') ?: [];
            $ids = array_map(static fn (string $f): string => basename($f, '.json'), $files);
            usort($ids, static function (string $a, string $b): int {
                $ia = array_search($a, self::ORDER, true);
                $ib = array_search($b, self::ORDER, true);
                return [$ia === false ? 99 : $ia, $a] <=> [$ib === false ? 99 : $ib, $b];
            });
            foreach ($ids as $id) {
                $data = json_decode((string) file_get_contents(__DIR__ . '/schemes/' . $id . '.json'), true);
                if (is_array($data)) {
                    self::$builtIn[$id] = self::normalize($data);
                }
            }
        }
        return self::$builtIn;
    }

    /** @return list<string> */
    public static function ids(): array
    {
        return array_keys(self::builtIn());
    }

    /** @return array<string, string>|null */
    public static function get(string $id): ?array
    {
        return self::builtIn()[$id] ?? null;
    }

    /**
     * Colours for a scheme setting: a built-in id, or 'custom' with the custom JSON.
     *
     * @return array<string, string>
     */
    public static function resolve(string $scheme, string $customJson = ''): array
    {
        if ($scheme === 'custom') {
            $custom = json_decode($customJson, true);
            return self::normalize(is_array($custom) ? $custom : []);
        }
        return self::get($scheme) ?? self::get(self::DEFAULT) ?? [];
    }

    /**
     * Keep the known keys with valid colours, filling gaps from the default scheme.
     *
     * @param array<mixed> $colors
     * @return array<string, string>
     */
    public static function normalize(array $colors): array
    {
        if (self::$builtIn === null) {
            self::builtIn();
        }
        $fallback = self::$builtIn[self::DEFAULT] ?? null;
        $out = [];
        foreach (self::KEYS as $key) {
            $value = $colors[$key] ?? null;
            if (is_string($value) && self::validColor($value)) {
                $out[$key] = strtolower($value);
            } elseif ($fallback !== null) {
                $out[$key] = $fallback[$key];
            } else {
                $out[$key] = '#000000';
            }
        }
        return $out;
    }

    /** Only #rgb and #rrggbb are accepted, so a value can never break out of CSS. */
    public static function validColor(string $value): bool
    {
        return preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value) === 1;
    }

    /**
     * CSS custom properties for a set of colours, e.g. ":root{--outside-bg:#fff;...}".
     *
     * @param array<string, string> $colors
     */
    public static function css(array $colors, string $selector = ':root'): string
    {
        $parts = [];
        foreach (self::normalize($colors) as $key => $value) {
            $parts[] = '--' . str_replace('_', '-', $key) . ':' . $value;
        }
        return $selector . '{' . implode(';', $parts) . '}';
    }
}
