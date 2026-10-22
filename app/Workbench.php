<?php

declare(strict_types=1);

namespace Angelma;

use Angelma\Repos\Pages;
use Angelma\Repos\Settings;

/**
 * The workbench: a hidden page that holds boxes which are not on any page
 * (PLAN.md section 6.14). Created the first time it is needed; its id is kept in
 * the workbench_page_id setting.
 */
final class Workbench
{
    public const SETTING = 'workbench_page_id';

    /** @return array<string, mixed> the workbench page, created on first use */
    public static function page(): array
    {
        $pages = new Pages();
        $id = self::id();
        $page = $id > 0 ? $pages->find($id) : null;
        if ($page !== null) {
            return $page;
        }
        $id = $pages->create([
            'slug' => slugify('workbench', static fn (string $s): bool => $pages->slugTaken($s)
                || in_array($s, Pages::RESERVED, true)),
            'name' => t('workbench_name'),
            'title' => t('workbench_name'),
            'meta_description' => '',
            'owner_id' => Auth::id(),
            'show_in_menu' => 0,
        ]);
        (new Settings())->set(self::SETTING, (string) $id);
        App::forgetSettings();
        return (array) $pages->find($id);
    }

    /** The workbench page id, or 0 while it does not exist yet. Does not create it. */
    public static function id(): int
    {
        return (int) App::setting(self::SETTING);
    }

    /** Is this page the workbench? */
    public static function is(int $pageId): bool
    {
        return $pageId > 0 && $pageId === self::id();
    }
}
