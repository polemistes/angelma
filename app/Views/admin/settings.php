<?php
/**
 * @var array<string, mixed> $values
 * @var array<string, string> $errors
 * @var array<string, array<string, string>> $schemes
 * @var list<array<string, mixed>> $pages
 * @var list<array<string, mixed>> $pickerImages
 * @var array<string, mixed>|null $logo
 * @var list<string> $timezones
 */
use Angelma\Schemes;

$err = static fn (string $name): string => partial('field_error', ['errors' => $errors, 'name' => $name]);
$custom = $values['custom'];
$selectedScheme = (string) $values['scheme'];
$previewColors = $selectedScheme === 'custom' ? $custom : ($schemes[$selectedScheme] ?? $custom);
$previewStyle = implode(';', array_map(
    static fn (string $k, string $v): string => '--' . str_replace('_', '-', $k) . ':' . $v,
    array_keys(Schemes::normalize($previewColors)),
    Schemes::normalize($previewColors),
));
$homeId = (int) $values['home_page_id'];
$pageCount = count($pages);
?>
<form method="post" action="<?= e(url('/admin/settings')) ?>" class="form settings-form">
    <?= csrf_field() ?>

    <section class="panel">
        <h2><?= e(t('settings_site')) ?></h2>
        <label>
            <span><?= e(t('field_site_title')) ?></span>
            <input type="text" name="site_title" value="<?= e($values['site_title']) ?>" required maxlength="100">
            <?= $err('site_title') ?>
        </label>
        <label>
            <span><?= e(t('settings_tagline')) ?></span>
            <input type="text" name="site_tagline" value="<?= e($values['site_tagline']) ?>" maxlength="200">
            <span class="hint"><?= e(t('settings_tagline_hint')) ?></span>
            <?= $err('site_tagline') ?>
        </label>
        <fieldset class="picker" data-picker="<?= e(url('/admin/images/picker')) ?>" data-picker-field="logo_image_id">
            <legend><?= e(t('settings_logo')) ?></legend>
            <div class="picker-current">
                <img src="<?= $logo !== null ? e(image_url((string) $logo['filename'], 's')) : '' ?>" alt="" data-picker-preview<?= $logo === null ? ' hidden' : '' ?>>
                <span data-picker-name<?= $logo === null ? ' class="muted"' : '' ?>><?= e($logo !== null ? $logo['original_name'] : t('settings_no_logo')) ?></span>
            </div>
            <label class="check">
                <input type="radio" name="logo_image_id" value="0" data-src="" data-name="<?= e(t('settings_no_logo')) ?>"<?= $logo === null ? ' checked' : '' ?>>
                <span><?= e(t('settings_no_logo')) ?></span>
            </label>
            <label class="picker-search">
                <span><?= e(t('picker_search')) ?></span>
                <input type="search" data-picker-search placeholder="<?= e(t('picker_search_placeholder')) ?>">
            </label>
            <div class="picker-list" data-picker-list>
                <?= partial('image_picker_list', [
                    'images' => $pickerImages,
                    'selected' => $logo !== null ? (int) $logo['id'] : 0,
                    'field' => 'logo_image_id',
                ]) ?>
            </div>
            <span class="hint"><?= e(t('settings_logo_hint')) ?></span>
            <?= $err('logo_image_id') ?>
        </fieldset>
        <label>
            <span><?= e(t('field_page_width')) ?></span>
            <input type="number" name="page_width" value="<?= e($values['page_width']) ?>" required min="600" max="1600" step="10">
            <?= $err('page_width') ?>
        </label>
    </section>

    <section class="panel" id="colours">
        <h2><?= e(t('settings_colours')) ?></h2>
        <div class="scheme-layout" data-schemes="<?= e((string) json_encode($schemes)) ?>">
            <div>
                <fieldset>
                    <legend><?= e(t('field_scheme')) ?></legend>
                    <div class="scheme-choices">
                        <?php foreach ($schemes as $id => $colors): ?>
                            <label class="scheme-choice">
                                <input type="radio" name="scheme" value="<?= e($id) ?>"<?= $selectedScheme === $id ? ' checked' : '' ?>>
                                <span class="swatch">
                                    <?php foreach (['outside_bg', 'page_bg', 'menu_bg', 'box_bg', 'link', 'text'] as $key): ?>
                                        <i style="background:<?= e($colors[$key]) ?>"></i>
                                    <?php endforeach ?>
                                </span>
                                <span><?= e(t('scheme_' . $id)) ?></span>
                            </label>
                        <?php endforeach ?>
                        <label class="scheme-choice">
                            <input type="radio" name="scheme" value="custom"<?= $selectedScheme === 'custom' ? ' checked' : '' ?>>
                            <span class="swatch">
                                <?php foreach (['outside_bg', 'page_bg', 'menu_bg', 'box_bg', 'link', 'text'] as $key): ?>
                                    <i style="background:<?= e($custom[$key] ?? '#000000') ?>" data-custom-swatch="<?= e($key) ?>"></i>
                                <?php endforeach ?>
                            </span>
                            <span><?= e(t('scheme_custom')) ?></span>
                        </label>
                    </div>
                    <?= $err('scheme') ?>
                </fieldset>

                <fieldset class="custom-scheme">
                    <legend><?= e(t('settings_custom_scheme')) ?></legend>
                    <p class="hint"><?= e(t('settings_custom_hint')) ?></p>
                    <div class="color-grid">
                        <?php foreach (Schemes::KEYS as $key): ?>
                            <label class="color-field">
                                <input type="color" name="custom[<?= e($key) ?>]" value="<?= e(Schemes::normalize($custom)[$key]) ?>" data-color-key="<?= e($key) ?>">
                                <span><?= e(t('color_' . $key)) ?></span>
                            </label>
                        <?php endforeach ?>
                    </div>
                    <?= $err('custom') ?>
                    <p><button type="submit" name="copy_scheme" value="1" class="button small secondary" data-copy-scheme><?= e(t('settings_copy_scheme')) ?></button></p>
                </fieldset>
            </div>

            <div class="scheme-preview-wrap">
                <span class="preview-label"><?= e(t('preview')) ?></span>
                <div class="scheme-preview" data-scheme-preview style="<?= e($previewStyle) ?>">
                    <div class="sp-page">
                        <div class="sp-header"><?= e($values['site_title']) ?></div>
                        <div class="sp-menu"><span class="sp-active"><?= e(t('settings_preview_home')) ?></span><span><?= e(t('settings_preview_about')) ?></span></div>
                        <div class="sp-body">
                            <div class="sp-box">
                                <div class="sp-heading"><?= e(t('settings_preview_heading')) ?></div>
                                <p><?= e(t('settings_preview_text')) ?> <span class="sp-link"><?= e(t('settings_preview_link')) ?></span>.</p>
                                <p class="sp-caption"><?= e(t('settings_preview_caption')) ?></p>
                            </div>
                        </div>
                        <div class="sp-footer"><?= e(t('powered_by')) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="panel">
        <h2><?= e(t('settings_footer')) ?></h2>
        <div class="markdown-editor">
            <label>
                <span><?= e(t('settings_footer_text')) ?></span>
                <textarea name="footer_markdown" rows="6" class="footer-text" data-preview-target="footer-preview"><?= e($values['footer_markdown']) ?></textarea>
                <span class="hint"><?= e(t('markdown_hint')) ?></span>
                <?= $err('footer_markdown') ?>
            </label>
            <div class="field">
                <span><?= e(t('preview')) ?></span>
                <div class="preview prose short-preview" id="footer-preview"><?= raw(\Angelma\Markdown::render((string) $values['footer_markdown'])) ?></div>
            </div>
        </div>
    </section>

    <section class="panel">
        <h2><?= e(t('settings_other')) ?></h2>
        <label>
            <span><?= e(t('settings_home_page')) ?></span>
            <select name="home_page_id">
                <?php foreach ($pages as $page): ?>
                    <option value="<?= (int) $page['id'] ?>"<?= (int) $page['id'] === $homeId ? ' selected' : '' ?>><?= e($page['name']) ?> (/<?= e($page['slug']) ?>)</option>
                <?php endforeach ?>
            </select>
            <?= $err('home_page_id') ?>
        </label>
        <label>
            <span><?= e(t('settings_timezone')) ?></span>
            <select name="timezone">
                <?php foreach ($timezones as $tz): ?>
                    <option<?= $tz === $values['timezone'] ? ' selected' : '' ?>><?= e($tz) ?></option>
                <?php endforeach ?>
            </select>
            <?= $err('timezone') ?>
        </label>
        <label>
            <span><?= e(t('settings_date_format')) ?></span>
            <input type="text" name="date_format" value="<?= e($values['date_format']) ?>" required maxlength="40">
            <span class="hint"><?= e(t('settings_date_format_hint', ['example' => format_date(now(), (string) $values['date_format'])])) ?></span>
            <?= $err('date_format') ?>
        </label>
        <label class="check">
            <input type="checkbox" name="moderate_anonymous" value="1"<?= $values['moderate_anonymous'] === '1' ? ' checked' : '' ?>>
            <span><?= e(t('settings_moderate')) ?></span>
        </label>
    </section>

    <section class="panel" id="maintenance">
        <h2><?= e(t('settings_maintenance_title')) ?></h2>
        <label class="check">
            <input type="checkbox" name="maintenance" value="1"<?= ($values['maintenance'] ?? '0') === '1' ? ' checked' : '' ?>>
            <span><?= e(t('settings_maintenance')) ?></span>
        </label>
        <label>
            <span><?= e(t('settings_maintenance_message')) ?></span>
            <textarea name="maintenance_message" rows="4"><?= e($values['maintenance_message'] ?? '') ?></textarea>
            <span class="hint"><?= e(t('settings_maintenance_hint')) ?></span>
            <?= $err('maintenance_message') ?>
        </label>
    </section>

    <p class="actions sticky-actions"><button type="submit" class="button"><?= e(t('settings_save')) ?></button></p>
</form>

<section class="panel" id="menu">
    <h2><?= e(t('settings_menu')) ?></h2>
    <p class="hint"><?= e(t('settings_menu_hint')) ?></p>
    <form method="post" action="<?= e(url('/admin/settings/menu')) ?>" id="menu-form">
        <?= csrf_field() ?>
    </form>
    <div class="table-wrap">
        <table class="list">
            <thead>
            <tr>
                <th><?= e(t('page_field_name')) ?></th>
                <th><?= e(t('pages_in_menu')) ?></th>
                <th><?= e(t('settings_order')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($pages as $i => $page): ?>
                <tr>
                    <td><?= e($page['name']) ?> <span class="muted small">/<?= e($page['slug']) ?></span></td>
                    <td><input type="checkbox" form="menu-form" name="show[]" value="<?= (int) $page['id'] ?>"
                               aria-label="<?= e(t('settings_show_in_menu', ['name' => (string) $page['name']])) ?>"<?= (int) $page['show_in_menu'] === 1 ? ' checked' : '' ?>></td>
                    <td class="actions-cell">
                        <?= partial('move_buttons', [
                            'action' => '/admin/settings/menu/' . $page['id'] . '/move',
                            'first' => $i === 0,
                            'last' => $i === $pageCount - 1,
                            'up' => t('move_up'),
                            'down' => t('move_down'),
                            'upIcon' => '↑',
                            'downIcon' => '↓',
                        ]) ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <p class="actions"><button type="submit" form="menu-form" class="button"><?= e(t('settings_menu_save')) ?></button></p>
</section>
