<?php

declare(strict_types=1);

namespace Angelma;

/**
 * Upload validation, re-encoding and resizing of images with GD.
 *
 * Every upload is decoded and written again, which drops metadata and anything hidden
 * in the file. PNG stays PNG (to keep transparency); everything else becomes JPEG.
 * Three files are written: large (long side at most 2000 px), medium (-m, 1000 px wide)
 * and small (-s, 400 px wide).
 */
final class Images
{
    public const MAX_BYTES = 20 * 1024 * 1024;
    public const MAX_PIXELS = 40_000_000;
    public const LARGE = 2000;
    public const MEDIUM = 1000;
    public const SMALL = 400;
    public const JPEG_QUALITY = 85;

    /** getimagesize() type => the MIME type finfo must report */
    private const TYPES = [
        IMAGETYPE_JPEG => 'image/jpeg',
        IMAGETYPE_PNG => 'image/png',
        IMAGETYPE_GIF => 'image/gif',
        IMAGETYPE_WEBP => 'image/webp',
    ];

    public function __construct(private string $dir)
    {
    }

    /** Largest accepted upload: the smaller of 20 MB and PHP's limits. */
    public static function maxUploadBytes(): int
    {
        $limits = [self::MAX_BYTES];
        foreach (['upload_max_filesize', 'post_max_size'] as $key) {
            $bytes = self::iniBytes((string) ini_get($key));
            if ($bytes > 0) {
                $limits[] = $bytes;
            }
        }
        return min($limits);
    }

    public static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || !preg_match('/^(\d+)\s*([KMG]?)/i', $value, $m)) {
            return 0;
        }
        return (int) $m[1] * match (strtoupper($m[2])) {
            'G' => 1024 ** 3,
            'M' => 1024 ** 2,
            'K' => 1024,
            default => 1,
        };
    }

    /**
     * Validate and store one uploaded file (an entry of $_FILES).
     *
     * @param array<string, mixed> $file
     * @return array{filename: string, original_name: string, mime: string, width: int, height: int, size: int}
     * @throws ImageException with a message for the user
     */
    public function storeUpload(array $file, bool $requireUploaded = true): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new ImageException(t('image_error_too_large', ['max' => self::megabytes(self::maxUploadBytes())]));
        }
        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new ImageException(t('image_error_no_file'));
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new ImageException(t('image_error_upload'));
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp) || ($requireUploaded && !is_uploaded_file($tmp))) {
            throw new ImageException(t('image_error_upload'));
        }
        if ((int) filesize($tmp) > self::maxUploadBytes()) {
            throw new ImageException(t('image_error_too_large', ['max' => self::megabytes(self::maxUploadBytes())]));
        }
        return $this->store($tmp, (string) ($file['name'] ?? 'image'));
    }

    /**
     * Validate, re-encode and resize an image file into the uploads folder.
     *
     * @return array{filename: string, original_name: string, mime: string, width: int, height: int, size: int}
     * @throws ImageException
     */
    public function store(string $path, string $originalName): array
    {
        $info = @getimagesize($path);
        if ($info === false || !isset(self::TYPES[$info[2]])) {
            throw new ImageException(t('image_error_type'));
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($path);
        if ($mime !== self::TYPES[$info[2]]) {
            throw new ImageException(t('image_error_type'));
        }
        if ($info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > self::MAX_PIXELS) {
            throw new ImageException(t('image_error_dimensions', ['max' => intdiv(self::MAX_PIXELS, 1_000_000)]));
        }

        self::raiseMemory($info[0] * $info[1]);
        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
        };
        if (!$image instanceof \GdImage) {
            throw new ImageException(t('image_error_type'));
        }

        if ($info[2] === IMAGETYPE_JPEG) {
            $image = self::orient($image, self::jpegOrientation($path));
        }

        $png = $info[2] === IMAGETYPE_PNG;
        if ($png) {
            imagepalettetotruecolor($image);
            imagealphablending($image, false);
            imagesavealpha($image, true);
        } else {
            $image = self::flatten($image);
        }
        $ext = $png ? 'png' : 'jpg';
        $base = bin2hex(random_bytes(6));
        while (is_file($this->dir . '/' . $base . '.' . $ext)) {
            $base = bin2hex(random_bytes(6));
        }

        $large = self::fitLongSide($image, self::LARGE);
        $files = [
            $base . '.' . $ext => $large,
            $base . '-m.' . $ext => self::fitWidth($large, self::MEDIUM),
            $base . '-s.' . $ext => self::fitWidth($large, self::SMALL),
        ];
        try {
            foreach ($files as $name => $img) {
                $target = $this->dir . '/' . $name;
                $ok = $png ? imagepng($img, $target, 6) : imagejpeg($img, $target, self::JPEG_QUALITY);
                if (!$ok) {
                    throw new ImageException(t('image_error_save'));
                }
            }
        } catch (\Throwable $e) {
            self::deleteFiles($this->dir, $base . '.' . $ext);
            throw $e instanceof ImageException ? $e : new ImageException(t('image_error_save'));
        }

        return [
            'filename' => $base . '.' . $ext,
            'original_name' => self::cleanName($originalName),
            'mime' => $png ? 'image/png' : 'image/jpeg',
            'width' => imagesx($large),
            'height' => imagesy($large),
            'size' => (int) filesize($this->dir . '/' . $base . '.' . $ext),
        ];
    }

    /** Remove the three files of an image. */
    public static function deleteFiles(string $dir, string $filename): void
    {
        if (!preg_match('/^[a-f0-9]{12}\.(jpg|png)$/', $filename)) {
            return;
        }
        [$base, $ext] = explode('.', $filename);
        foreach ([$base . '.' . $ext, $base . '-m.' . $ext, $base . '-s.' . $ext] as $name) {
            if (is_file($dir . '/' . $name)) {
                unlink($dir . '/' . $name);
            }
        }
    }

    /** Scale down so the longer side is at most $max pixels. */
    public static function fitLongSide(\GdImage $image, int $max): \GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        if (max($w, $h) <= $max) {
            return $image;
        }
        $scale = $max / max($w, $h);
        return self::resize($image, max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
    }

    /** Scale down so the width is at most $max pixels. */
    public static function fitWidth(\GdImage $image, int $max): \GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        if ($w <= $max) {
            return $image;
        }
        return self::resize($image, $max, max(1, (int) round($h * $max / $w)));
    }

    public static function resize(\GdImage $image, int $width, int $height): \GdImage
    {
        $out = imagecreatetruecolor($width, $height);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
        return $out;
    }

    /**
     * EXIF orientation of a JPEG (1 to 8; 1 means upright). Uses the exif extension
     * when present, otherwise reads the APP1 segment directly.
     */
    public static function jpegOrientation(string $path): int
    {
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            $value = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
            return $value >= 1 && $value <= 8 ? $value : 1;
        }

        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return 1;
        }
        try {
            if (fread($fh, 2) !== "\xFF\xD8") {
                return 1;
            }
            while (!feof($fh)) {
                $marker = fread($fh, 2);
                if (strlen($marker) < 2 || $marker[0] !== "\xFF") {
                    return 1;
                }
                $len = unpack('n', (string) fread($fh, 2))[1] ?? 0;
                if ($len < 2) {
                    return 1;
                }
                if ($marker[1] === "\xE1") {
                    return self::tiffOrientation((string) fread($fh, $len - 2));
                }
                if ($marker[1] === "\xDA") {
                    return 1;
                }
                fseek($fh, $len - 2, SEEK_CUR);
            }
        } finally {
            fclose($fh);
        }
        return 1;
    }

    /** Orientation tag (0x0112) from an APP1 "Exif" segment. */
    private static function tiffOrientation(string $data): int
    {
        if (!str_starts_with($data, "Exif\0\0") || strlen($data) < 14) {
            return 1;
        }
        $tiff = substr($data, 6);
        $little = substr($tiff, 0, 2) === 'II';
        $u16 = static fn (int $o): int => (int) (unpack($little ? 'v' : 'n', substr($tiff, $o, 2))[1] ?? 0);
        $u32 = static fn (int $o): int => (int) (unpack($little ? 'V' : 'N', substr($tiff, $o, 4))[1] ?? 0);
        $ifd = $u32(4);
        if ($ifd + 2 > strlen($tiff)) {
            return 1;
        }
        $count = $u16($ifd);
        for ($i = 0; $i < $count; $i++) {
            $entry = $ifd + 2 + $i * 12;
            if ($entry + 12 > strlen($tiff)) {
                break;
            }
            if ($u16($entry) === 0x0112) {
                $value = $u16($entry + 8);
                return $value >= 1 && $value <= 8 ? $value : 1;
            }
        }
        return 1;
    }

    /** Rotate and flip an image according to an EXIF orientation value. */
    public static function orient(\GdImage $image, int $orientation): \GdImage
    {
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }
        $angle = match ($orientation) {
            3, 4 => 180,
            6, 7 => 270,
            5, 8 => 90,
            default => 0,
        };
        if ($angle !== 0) {
            $rotated = imagerotate($image, $angle, 0);
            if ($rotated instanceof \GdImage) {
                $image = $rotated;
            }
        }
        return $image;
    }

    /** Put an image on a white background (for JPEG output, which has no transparency). */
    private static function flatten(\GdImage $image): \GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $out = imagecreatetruecolor($w, $h);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopy($out, $image, 0, 0, 0, 0, $w, $h);
        return $out;
    }

    private static function raiseMemory(int $pixels): void
    {
        $needed = $pixels * 12 + 64 * 1024 * 1024;
        $limit = self::iniBytes((string) ini_get('memory_limit'));
        if ($limit > 0 && $limit < $needed) {
            @ini_set('memory_limit', (string) $needed);
        }
    }

    private static function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = (string) preg_replace('/[\x00-\x1f\x7f]/u', '', $name);
        $name = mb_substr(trim($name), 0, 200);
        return $name !== '' ? $name : 'image';
    }

    public static function megabytes(int $bytes): string
    {
        return rtrim(rtrim(number_format($bytes / 1048576, 1, '.', ''), '0'), '.');
    }
}
