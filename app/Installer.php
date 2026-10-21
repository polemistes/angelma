<?php

declare(strict_types=1);

namespace Angelma;

use Angelma\Repos\Boxes;
use Angelma\Repos\Pages;
use Angelma\Repos\Rows;
use Angelma\Repos\Settings;
use Angelma\Repos\Users;

/**
 * The work behind the browser installer (install/index.php): requirement checks,
 * validation and creating the site.
 */
final class Installer
{
    public const MIN_PHP = '8.2.0';
    public const REQUIRED_EXTENSIONS = ['pdo_sqlite', 'gd', 'mbstring', 'json', 'session'];
    public const OPTIONAL_EXTENSIONS = ['pdo_mysql', 'zip'];
    public const MIN_PASSWORD = Auth::MIN_PASSWORD;

    public function __construct(private string $root)
    {
    }

    /**
     * @return list<array{label: string, ok: bool, required: bool, detail: string}>
     */
    public function requirements(): array
    {
        $checks = [[
            'label' => t('install_check_php', ['version' => self::MIN_PHP]),
            'ok' => version_compare(PHP_VERSION, self::MIN_PHP, '>='),
            'required' => true,
            'detail' => PHP_VERSION,
        ]];
        foreach (self::REQUIRED_EXTENSIONS as $ext) {
            $checks[] = [
                'label' => t('install_check_ext', ['ext' => $ext]),
                'ok' => extension_loaded($ext),
                'required' => true,
                'detail' => '',
            ];
        }
        foreach (self::OPTIONAL_EXTENSIONS as $ext) {
            $checks[] = [
                'label' => t('install_check_ext_optional', ['ext' => $ext]),
                'ok' => extension_loaded($ext),
                'required' => false,
                'detail' => '',
            ];
        }
        foreach (['data', 'data/sessions', 'uploads'] as $dir) {
            $checks[] = [
                'label' => t('install_check_writable', ['dir' => $dir . '/']),
                'ok' => is_dir($this->root . '/' . $dir) && is_writable($this->root . '/' . $dir),
                'required' => true,
                'detail' => '',
            ];
        }
        return $checks;
    }

    public function requirementsMet(): bool
    {
        foreach ($this->requirements() as $check) {
            if ($check['required'] && !$check['ok']) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<string, string> $input site_title, page_width, scheme
     * @return array<string, string> field => error message
     */
    public static function validateSite(array $input): array
    {
        $errors = [];
        $title = trim($input['site_title'] ?? '');
        if ($title === '' || mb_strlen($title) > 100) {
            $errors['site_title'] = t('install_error_title');
        }
        $width = $input['page_width'] ?? '';
        if (!preg_match('/^\d+$/', $width) || (int) $width < 600 || (int) $width > 1600) {
            $errors['page_width'] = t('error_page_width');
        }
        if (Schemes::get($input['scheme'] ?? '') === null) {
            $errors['scheme'] = t('install_error_scheme');
        }
        return $errors;
    }

    /**
     * @param array<string, string> $input username, display_name, password, password_repeat
     * @return array<string, string> field => error message
     */
    public static function validateAdmin(array $input): array
    {
        $errors = [];
        $username = trim($input['username'] ?? '');
        if (!self::validUsername($username)) {
            $errors['username'] = t('error_username');
        }
        $display = trim($input['display_name'] ?? '');
        if ($display === '' || mb_strlen($display) > 80) {
            $errors['display_name'] = t('error_display_name');
        }
        $password = $input['password'] ?? '';
        if (mb_strlen($password) < self::MIN_PASSWORD) {
            $errors['password'] = t('error_password_short', ['min' => self::MIN_PASSWORD]);
        } elseif ($password !== ($input['password_repeat'] ?? '')) {
            $errors['password_repeat'] = t('error_password_repeat');
        }
        return $errors;
    }

    public static function validUsername(string $username): bool
    {
        return preg_match('/^[A-Za-z0-9._-]{3,40}$/', $username) === 1;
    }

    /**
     * Create config, database, settings, administrator, home page and lock file.
     *
     * @param array<string, string> $site site_title, page_width, scheme
     * @param array<string, string> $admin username, display_name, password_hash
     */
    public function install(array $site, array $admin): void
    {
        $dbRelative = 'data/angelma.sqlite';
        $dbPath = $this->root . '/' . $dbRelative;

        $db = Db::sqlite($dbPath);
        $db->migrate();
        if ((new Users($db))->count() > 0) {
            throw new \RuntimeException(t('install_error_existing_db'));
        }

        $db->transaction(function (Db $db) use ($site, $admin): void {
            $scheme = Schemes::get($site['scheme']) ?? Schemes::resolve(Schemes::DEFAULT);
            $settings = new Settings($db);
            $settings->setMany(array_merge(App::DEFAULT_SETTINGS, [
                'site_title' => trim($site['site_title']),
                'page_width' => (string) (int) $site['page_width'],
                'scheme' => $site['scheme'],
                'custom_scheme' => (string) json_encode($scheme),
            ]));

            $userId = (new Users($db))->create([
                'username' => trim($admin['username']),
                'display_name' => trim($admin['display_name']),
                'password_hash' => $admin['password_hash'],
                'role' => 'admin',
                'is_active' => 1,
            ]);

            $pageId = (new Pages($db))->create([
                'slug' => 'home',
                'name' => 'Home',
                'title' => 'Welcome',
                'owner_id' => $userId,
            ]);
            $rowId = (new Rows($db))->create($pageId, 'left');
            (new Boxes($db))->create($rowId, 'text', 12, ['markdown' => t('install_welcome_markdown')]);
            $settings->set('home_page_id', (string) $pageId);
        });

        $config = [
            'secret' => bin2hex(random_bytes(32)),
            'db_path' => $dbRelative,
            'debug' => false,
        ];
        $php = "<?php\n\n// Angelma configuration, written by the installer.\n\nreturn " . var_export($config, true) . ";\n";
        if (file_put_contents($this->root . '/data/config.php', $php, LOCK_EX) === false) {
            throw new \RuntimeException(t('install_error_write', ['file' => 'data/config.php']));
        }
        if (file_put_contents($this->root . '/data/installed.lock', now() . "\n", LOCK_EX) === false) {
            throw new \RuntimeException(t('install_error_write', ['file' => 'data/installed.lock']));
        }
    }
}
