<?php

declare(strict_types=1);

namespace Angelma;

/**
 * Turns a pasted URL from a supported provider into an iframe address
 * (PLAN.md section 6.6). Unrecognised URLs are shown as plain links.
 */
final class Embed
{
    /** The sandbox every embed iframe gets. */
    public const SANDBOX = 'allow-scripts allow-same-origin allow-popups allow-presentation';

    /**
     * @return array{provider: string, src: string, ratio: string}|null
     */
    public static function parse(string $url): ?array
    {
        $url = trim($url);
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host']) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }
        $host = strtolower((string) preg_replace('/^(www\.|m\.)/', '', $parts['host']));
        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $query);

        // YouTube: youtube.com/watch?v=ID, youtu.be/ID, youtube.com/embed/ID
        $youtubeId = null;
        if ($host === 'youtube.com' && $path === '/watch' && is_string($query['v'] ?? null)) {
            $youtubeId = $query['v'];
        } elseif ($host === 'youtu.be') {
            $youtubeId = ltrim($path, '/');
        } elseif (($host === 'youtube.com' || $host === 'youtube-nocookie.com') && preg_match('#^/(embed|shorts)/([^/]+)#', $path, $m)) {
            $youtubeId = $m[2];
        }
        if ($youtubeId !== null) {
            return preg_match('/^[A-Za-z0-9_-]{11}$/', $youtubeId)
                ? ['provider' => 'youtube', 'src' => 'https://www.youtube.com/embed/' . $youtubeId, 'ratio' => 'video']
                : null;
        }

        // Vimeo: vimeo.com/123456, player.vimeo.com/video/123456
        if (($host === 'vimeo.com' && preg_match('#^/(?:.*/)?(\d+)/?$#', $path, $m))
            || ($host === 'player.vimeo.com' && preg_match('#^/video/(\d+)#', $path, $m))) {
            return ['provider' => 'vimeo', 'src' => 'https://player.vimeo.com/video/' . $m[1], 'ratio' => 'video'];
        }

        // Google Maps embed: www.google.com/maps/embed?pb=...
        if ($host === 'google.com' && str_starts_with($path, '/maps/embed') && isset($parts['query'])) {
            return [
                'provider' => 'google_maps',
                'src' => 'https://www.google.com' . $path . '?' . $parts['query'],
                'ratio' => 'map',
            ];
        }

        // OpenStreetMap export: www.openstreetmap.org/export/embed.html?bbox=...
        if ($host === 'openstreetmap.org' && $path === '/export/embed.html' && isset($parts['query'])) {
            return [
                'provider' => 'openstreetmap',
                'src' => 'https://www.openstreetmap.org/export/embed.html?' . $parts['query'],
                'ratio' => 'map',
            ];
        }

        return null;
    }

    /** True for an absolute http or https URL. */
    public static function isHttpUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && preg_match('#^https?://#i', $url) === 1;
    }
}
