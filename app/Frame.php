<?php

declare(strict_types=1);

namespace Angelma;

use Angelma\Repos\Images;
use Angelma\Repos\Pages;

/**
 * Data for the public page frame (header, menu, colours, footer), used by
 * layout/site.php on every public page.
 */
final class Frame
{
    /** @return array<string, mixed> */
    public static function site(?int $currentPageId = null): array
    {
        $frame = [
            'site_title' => App::setting('site_title'),
            'tagline' => App::setting('site_tagline'),
            'logo' => null,
            'menu' => [],
            'home_page_id' => (int) App::setting('home_page_id'),
            'current_page_id' => $currentPageId,
            'page_width' => max(600, min(1600, (int) App::setting('page_width', '960'))),
            'scheme_css' => Schemes::css(Schemes::resolve(App::setting('scheme'), App::setting('custom_scheme'))),
            'footer_html' => Markdown::render(App::setting('footer_markdown')),
            'user' => App::$request?->user,
        ];

        if (!App::installed() && App::$request !== null) {
            return $frame;
        }

        try {
            $frame['menu'] = (new Pages())->menu();
            $logoId = (int) App::setting('logo_image_id');
            if ($logoId > 0) {
                $frame['logo'] = (new Images())->find($logoId);
            }
        } catch (\PDOException $e) {
            error_log((string) $e);
        }
        return $frame;
    }
}
