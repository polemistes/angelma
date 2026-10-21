<?php

declare(strict_types=1);

namespace Angelma\Controllers\Admin;

use Angelma\App;
use Angelma\Auth;
use Angelma\Request;
use Angelma\Response;

/**
 * Download a zip of the database and the uploads folder. Administrators only.
 */
final class BackupController
{
    public function show(Request $request): Response
    {
        Auth::requireRole('admin');
        return view('admin/backup', [
            'title' => t('nav_backup'),
            'nav' => 'backup',
            'zipAvailable' => class_exists(\ZipArchive::class),
        ]);
    }

    public function download(Request $request): Response
    {
        Auth::requireRole('admin');
        if (!class_exists(\ZipArchive::class)) {
            flash('error', t('backup_no_zip'));
            return redirect('/admin/backup');
        }

        $dataDir = App::$root . '/data';
        self::removeStale($dataDir);
        $token = bin2hex(random_bytes(8));
        $dbCopy = $dataDir . '/backup-' . $token . '.sqlite';
        $zipPath = $dataDir . '/backup-' . $token . '.zip';

        try {
            App::db()->copyTo($dbCopy);
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::EXCL) !== true) {
                throw new \RuntimeException('Could not create ' . $zipPath);
            }
            $zip->addFile($dbCopy, 'data/angelma.sqlite');
            $uploads = App::$root . '/uploads';
            $zip->addEmptyDir('uploads');
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($uploads, \FilesystemIterator::SKIP_DOTS),
            );
            foreach ($files as $file) {
                /** @var \SplFileInfo $file */
                if ($file->isFile()) {
                    $relative = substr($file->getPathname(), strlen($uploads) + 1);
                    $zip->addFile($file->getPathname(), 'uploads/' . str_replace('\\', '/', $relative));
                }
            }
            if (!$zip->close()) {
                throw new \RuntimeException('Could not write ' . $zipPath);
            }
        } catch (\Throwable $e) {
            @unlink($zipPath);
            error_log((string) $e);
            flash('error', t('backup_failed'));
            return redirect('/admin/backup');
        } finally {
            @unlink($dbCopy);
        }

        $name = 'angelma-backup-' . format_date(now(), 'Y-m-d') . '.zip';
        return Response::download($zipPath, $name, 'application/zip', true);
    }

    /** Remove backup files left behind by an interrupted download. */
    private static function removeStale(string $dir): void
    {
        foreach (glob($dir . '/backup-*.{zip,sqlite}', GLOB_BRACE) ?: [] as $file) {
            if (filemtime($file) < time() - 3600) {
                @unlink($file);
            }
        }
    }
}
