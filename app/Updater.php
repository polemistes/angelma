<?php

declare(strict_types=1);

namespace Angelma;

use Angelma\Repos\Settings;

/**
 * Updates from GitHub releases (PLAN.md section 6.16): check for a newer version,
 * download and verify the release, apply it over the site, and restore the previous
 * version. Everything that touches the site's files goes through apply().
 */
final class Updater
{
    public const REPO = 'polemistes/angelma';
    public const API_BASE = 'https://api.github.com/repos/';
    public const DOWNLOAD_BASE = 'https://github.com/';
    public const CHECK_INTERVAL = 86400;
    public const SETTING = 'update_check';
    public const LOCK = 'data/updating.lock';
    public const UPDATES_DIR = 'data/updates';
    /** Folders a release replaces, in the order they are copied. */
    public const FOLDERS = ['vendor', 'app', 'assets', 'install'];
    /** Folders where files the release no longer has are removed. */
    public const CLEANED = ['vendor', 'app', 'assets'];
    public const MIN_FREE_BYTES = 50 * 1024 * 1024;

    public function __construct(
        private string $root,
        private string $repo = self::REPO,
        private string $apiBase = self::API_BASE,
        private string $downloadBase = self::DOWNLOAD_BASE,
    ) {
    }

    /** The updater for this site, with the overrides from data/config.php. */
    public static function fromApp(): self
    {
        $config = App::$config;
        return new self(
            App::$root,
            (string) ($config['update_repo'] ?? self::REPO),
            (string) ($config['update_api_base'] ?? self::API_BASE),
            (string) ($config['update_download_base'] ?? self::DOWNLOAD_BASE),
        );
    }

    public function currentVersion(): string
    {
        return (string) require $this->root . '/app/version.php';
    }

    public function releasePageUrl(): string
    {
        return $this->downloadBase . $this->repo . '/releases/latest';
    }

    /** Can PHP download from the web at all? */
    public static function canFetch(): bool
    {
        return function_exists('curl_init') || filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL);
    }

    // ---- Checking

    /**
     * The newest release, from the cache unless it is older than a day or $force is set.
     *
     * @return array<string, mixed> version, newer, notes, html_url, published_at, zip_url,
     *     sum_url, current, checked_at; or error and checked_at when the check failed
     */
    public function check(bool $force = false): array
    {
        $cached = json_decode(App::setting(self::SETTING), true);
        if (!$force && is_array($cached) && time() - (int) ($cached['checked_at'] ?? 0) < self::CHECK_INTERVAL
            && ($cached['current'] ?? '') === $this->currentVersion()) {
            return $cached;
        }
        try {
            $json = json_decode($this->fetch($this->apiBase . $this->repo . '/releases/latest', 8), true);
            $result = $this->parseRelease(is_array($json) ? $json : []);
        } catch (\Throwable $e) {
            $result = ['error' => $e->getMessage()];
        }
        $result['current'] = $this->currentVersion();
        $result['checked_at'] = time();
        (new Settings())->set(self::SETTING, (string) json_encode($result));
        App::forgetSettings();
        return $result;
    }

    /**
     * Read a GitHub release. Only assets under the repository's download address count.
     *
     * @param array<string, mixed> $json
     * @return array<string, mixed>
     */
    public function parseRelease(array $json): array
    {
        $version = ltrim((string) ($json['tag_name'] ?? ''), 'v');
        if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            throw new \RuntimeException(t('update_error_no_release'));
        }
        $prefix = $this->downloadBase . $this->repo . '/releases/download/';
        $zip = $sum = null;
        foreach (is_array($json['assets'] ?? null) ? $json['assets'] : [] as $asset) {
            $name = (string) ($asset['name'] ?? '');
            $url = (string) ($asset['browser_download_url'] ?? '');
            if (!str_starts_with($url, $prefix)) {
                continue;
            }
            if ($name === "angelma-$version.zip") {
                $zip = $url;
            } elseif ($name === "angelma-$version.zip.sha256") {
                $sum = $url;
            }
        }
        return [
            'version' => $version,
            'newer' => version_compare($version, $this->currentVersion(), '>'),
            'notes' => (string) ($json['body'] ?? ''),
            'html_url' => (string) ($json['html_url'] ?? ''),
            'published_at' => (string) ($json['published_at'] ?? ''),
            'zip_url' => $zip,
            'sum_url' => $sum,
        ];
    }

    // ---- Preflight

    /** @return list<array{key: string, ok: bool, detail: string}> */
    public function preflight(): array
    {
        $unwritable = $this->unwritable();
        $free = @disk_free_space($this->root);
        $dir = $this->root . '/' . self::UPDATES_DIR;
        return [
            ['key' => 'fetch', 'ok' => self::canFetch(), 'detail' => function_exists('curl_init') ? 'curl' : (self::canFetch() ? 'allow_url_fopen' : '')],
            ['key' => 'zip', 'ok' => class_exists(\ZipArchive::class), 'detail' => ''],
            ['key' => 'writable', 'ok' => $unwritable === [], 'detail' => implode(', ', array_slice($unwritable, 0, 5))],
            ['key' => 'space', 'ok' => $free === false || $free >= self::MIN_FREE_BYTES, 'detail' => $free === false ? '' : round($free / 1048576) . ' MB'],
            ['key' => 'updates_dir', 'ok' => (is_dir($dir) || @mkdir($dir, 0775, true)) && is_writable($dir), 'detail' => self::UPDATES_DIR],
        ];
    }

    /**
     * Folders PHP cannot write into: the root and every folder a release replaces.
     * Files are replaced by renaming a copy into place, so folder permissions decide.
     *
     * @return list<string>
     */
    public function unwritable(): array
    {
        $bad = [];
        if (!is_writable($this->root)) {
            $bad[] = '.';
        }
        foreach (self::FOLDERS as $folder) {
            foreach (self::directories($this->root, $folder) as $rel) {
                if (!is_writable($this->root . '/' . $rel)) {
                    $bad[] = $rel;
                }
            }
        }
        return $bad;
    }

    // ---- Installing

    /**
     * Download, verify, extract and apply a release. Nothing is replaced before the
     * archive has been verified and checked.
     *
     * @param array<string, mixed> $release from check()
     * @return array<string, mixed> version, copied, removed
     */
    public function install(array $release): array
    {
        $version = (string) ($release['version'] ?? '');
        if (!preg_match('/^\d+\.\d+\.\d+$/', $version) || empty($release['zip_url']) || empty($release['sum_url'])) {
            throw new \RuntimeException(t('update_error_assets'));
        }
        @set_time_limit(300);
        ignore_user_abort(true);
        $zip = $this->updatesDir() . "/angelma-$version.zip";
        $this->fetch((string) $release['zip_url'], 120, $zip);
        $checksum = $this->fetch((string) $release['sum_url'], 20);
        return $this->installFromZip($zip, $checksum, $version);
    }

    /**
     * Verify a downloaded zip against the checksum text, extract it, check it and apply it.
     *
     * @return array<string, mixed>
     */
    public function installFromZip(string $zip, string $checksumText, string $version): array
    {
        self::verifyChecksum($zip, $checksumText);
        $unpacked = $this->updatesDir() . '/unpacked';
        self::removeTree($unpacked);
        self::extract($zip, $unpacked);
        $this->validateTree($unpacked, $version);
        $this->backupCurrent();
        $summary = $this->apply($unpacked);
        self::removeTree($unpacked);
        foreach (glob($this->updatesDir() . '/angelma-*.zip') ?: [] as $old) {
            if ($old !== $zip) {
                @unlink($old);
            }
        }
        return $summary + ['version' => $version];
    }

    /** The checksum text is in sha256sum's format: the hex digest, then the file name. */
    public static function verifyChecksum(string $file, string $checksumText): void
    {
        if (!preg_match('/\b([0-9a-f]{64})\b/i', $checksumText, $m)) {
            throw new \RuntimeException(t('update_error_checksum_format'));
        }
        if (!hash_equals(strtolower($m[1]), (string) hash_file('sha256', $file))) {
            throw new \RuntimeException(t('update_error_checksum'));
        }
    }

    /** Extract a zip entry by entry, refusing any path that would leave the folder. */
    public static function extract(string $zip, string $to): void
    {
        $archive = new \ZipArchive();
        if ($archive->open($zip) !== true) {
            throw new \RuntimeException(t('update_error_zip'));
        }
        if (!is_dir($to)) {
            mkdir($to, 0775, true);
        }
        try {
            for ($i = 0; $i < $archive->numFiles; $i++) {
                $name = (string) $archive->getNameIndex($i);
                if ($name === '' || $name[0] === '/' || str_contains($name, '\\') || str_contains($name, "\0")
                    || preg_match('#(^|/)\.\.(/|$)#', $name)) {
                    throw new \RuntimeException(t('update_error_zip_path', ['name' => $name]));
                }
                $target = $to . '/' . $name;
                if (str_ends_with($name, '/')) {
                    if (!is_dir($target)) {
                        mkdir($target, 0775, true);
                    }
                    continue;
                }
                if (!is_dir(dirname($target))) {
                    mkdir(dirname($target), 0775, true);
                }
                $in = $archive->getStream($name);
                $out = $in === false ? false : fopen($target, 'wb');
                if ($in === false || $out === false) {
                    throw new \RuntimeException(t('update_error_zip'));
                }
                stream_copy_to_stream($in, $out);
                fclose($out);
                fclose($in);
            }
        } finally {
            $archive->close();
        }
    }

    /** The extracted tree must be Angelma of the expected version and run on this PHP. */
    private function validateTree(string $dir, string $version): void
    {
        if (!is_file("$dir/index.php") || !is_file("$dir/app/version.php") || !is_dir("$dir/vendor")) {
            throw new \RuntimeException(t('update_error_not_angelma'));
        }
        $found = (string) require "$dir/app/version.php";
        if ($found !== $version) {
            throw new \RuntimeException(t('update_error_version', ['expected' => $version, 'found' => $found]));
        }
        $composer = json_decode((string) @file_get_contents("$dir/composer.json"), true);
        $needs = (string) ($composer['require']['php'] ?? '');
        if (preg_match('/(\d+\.\d+(?:\.\d+)?)/', $needs, $m) && version_compare(PHP_VERSION, $m[1], '<')) {
            throw new \RuntimeException(t('update_error_php', ['needed' => $m[1], 'have' => PHP_VERSION]));
        }
    }

    /** Copy the current code to data/updates/previous so it can be restored. */
    public function backupCurrent(): void
    {
        $previous = $this->updatesDir() . '/previous';
        self::removeTree($previous);
        mkdir($previous, 0775, true);
        foreach (self::rootFiles($this->root) as $name) {
            copy($this->root . '/' . $name, $previous . '/' . $name);
        }
        foreach (self::FOLDERS as $folder) {
            if (is_dir($this->root . '/' . $folder)) {
                self::copyTree($this->root . '/' . $folder, $previous . '/' . $folder);
            }
        }
    }

    public function previousVersion(): ?string
    {
        $file = $this->updatesDir() . '/previous/app/version.php';
        return is_file($file) ? (string) require $file : null;
    }

    /** @return array<string, mixed> */
    public function rollback(): array
    {
        $previous = $this->updatesDir() . '/previous';
        if (!is_file("$previous/index.php") || !is_file("$previous/app/version.php")) {
            throw new \RuntimeException(t('update_error_no_previous'));
        }
        return $this->apply($previous) + ['version' => (string) require "$previous/app/version.php"];
    }

    /**
     * Copy a release tree over the site: the folders first (vendor, app, assets, and
     * install when it still exists), removing stale files in vendor, app and assets,
     * then the root files with index.php last. The lock file makes index.php answer
     * 503 meanwhile. data/ and uploads/ are never touched.
     *
     * @return array<string, int> copied, removed
     */
    public function apply(string $source): array
    {
        $lock = $this->root . '/' . self::LOCK;
        $copied = $removed = 0;
        file_put_contents($lock, (string) time());
        try {
            foreach (self::FOLDERS as $folder) {
                if (!is_dir("$source/$folder") || ($folder === 'install' && !is_dir($this->root . '/' . $folder))) {
                    continue;
                }
                $kept = [];
                foreach (self::files($source, $folder) as $rel) {
                    self::replaceFile("$source/$rel", $this->root . '/' . $rel);
                    $kept[$rel] = true;
                    $copied++;
                }
                if (in_array($folder, self::CLEANED, true)) {
                    foreach (self::files($this->root, $folder) as $rel) {
                        if (!isset($kept[$rel])) {
                            @unlink($this->root . '/' . $rel);
                            $removed++;
                        }
                    }
                    self::removeEmptyDirs($this->root . '/' . $folder);
                }
            }
            $files = self::rootFiles($source);
            usort($files, static fn (string $a, string $b): int => ($a === 'index.php') <=> ($b === 'index.php'));
            foreach ($files as $name) {
                self::replaceFile("$source/$name", $this->root . '/' . $name);
                $copied++;
            }
        } finally {
            @unlink($lock);
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        return ['copied' => $copied, 'removed' => $removed];
    }

    // ---- Files

    private function updatesDir(): string
    {
        $dir = $this->root . '/' . self::UPDATES_DIR;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /** Write a copy next to the target, then rename it into place, so readers never see a half-written file. */
    private static function replaceFile(string $from, string $to): void
    {
        if (!is_dir(dirname($to))) {
            mkdir(dirname($to), 0775, true);
        }
        $tmp = $to . '.angelma-tmp';
        if (!copy($from, $tmp) || !rename($tmp, $to)) {
            @unlink($tmp);
            throw new \RuntimeException(t('update_error_write', ['file' => $to]));
        }
    }

    /** @return list<string> regular files directly in a folder, by name */
    private static function rootFiles(string $dir): array
    {
        $files = [];
        foreach (scandir($dir) ?: [] as $name) {
            if ($name !== '.' && $name !== '..' && !str_ends_with($name, '.angelma-tmp') && is_file($dir . '/' . $name)) {
                $files[] = $name;
            }
        }
        return $files;
    }

    /** @return list<string> every file under $base/$folder as a path relative to $base */
    private static function files(string $base, string $folder): array
    {
        return self::walk($base, $folder, false);
    }

    /** @return list<string> $folder and every folder under it, relative to $base */
    private static function directories(string $base, string $folder): array
    {
        return is_dir($base . '/' . $folder) ? array_merge([$folder], self::walk($base, $folder, true)) : [];
    }

    /** @return list<string> */
    private static function walk(string $base, string $folder, bool $directories): array
    {
        $out = [];
        $root = $base . '/' . $folder;
        if (!is_dir($root)) {
            return $out;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $path => $info) {
            if ($info->isDir() === $directories) {
                $out[] = $folder . '/' . substr((string) $path, strlen($root) + 1);
            }
        }
        sort($out);
        return $out;
    }

    private static function copyTree(string $from, string $to): void
    {
        mkdir($to, 0775, true);
        foreach (scandir($from) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            if (is_dir("$from/$name")) {
                self::copyTree("$from/$name", "$to/$name");
            } else {
                copy("$from/$name", "$to/$name");
            }
        }
    }

    public static function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            if (is_dir("$dir/$name") && !is_link("$dir/$name")) {
                self::removeTree("$dir/$name");
            } else {
                @unlink("$dir/$name");
            }
        }
        @rmdir($dir);
    }

    private static function removeEmptyDirs(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $name) {
            if ($name !== '.' && $name !== '..' && is_dir("$dir/$name")) {
                self::removeEmptyDirs("$dir/$name");
                @rmdir("$dir/$name");
            }
        }
    }

    // ---- HTTP

    /** GET a URL with curl or the stream wrapper; writes to $toFile when given. Throws unless the answer is 200. */
    private function fetch(string $url, int $timeout, ?string $toFile = null): string
    {
        $agent = 'Angelma/' . $this->currentVersion() . ' (+https://github.com/' . self::REPO . ')';
        $headers = ['Accept: application/vnd.github+json, */*', 'User-Agent: ' . $agent];
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $out = $toFile !== null ? fopen($toFile, 'wb') : null;
            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => $out === null,
            ] + ($out !== null ? [CURLOPT_FILE => $out] : []));
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            if ($out !== null) {
                fclose($out);
            }
            if ($body === false || $code !== 200) {
                if ($toFile !== null) {
                    @unlink($toFile);
                }
                throw new \RuntimeException(t('update_error_fetch', ['url' => $url, 'detail' => $error !== '' ? $error : 'HTTP ' . $code]));
            }
            return $toFile !== null ? '' : (string) $body;
        }
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers) . "\r\n",
            'timeout' => $timeout,
            'follow_location' => 1,
            'max_redirects' => 5,
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($url, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
                $status = (int) $m[1];
            }
        }
        if ($body === false || $status !== 200) {
            throw new \RuntimeException(t('update_error_fetch', ['url' => $url, 'detail' => 'HTTP ' . $status]));
        }
        if ($toFile !== null) {
            file_put_contents($toFile, $body);
            return '';
        }
        return $body;
    }
}
