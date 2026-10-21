<?php

declare(strict_types=1);

namespace Angelma\Tests;

use Angelma\ImageException;
use Angelma\Images;
use PHPUnit\Framework\TestCase;

final class ImagesTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/angelma-img-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    private function makeJpeg(int $w, int $h, ?int $orientation = null): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 0, 0, 255));
        // Red marker in the top-left corner.
        imagefilledrectangle($img, 0, 0, intdiv($w, 4), intdiv($h, 4), imagecolorallocate($img, 255, 0, 0));
        $path = $this->dir . '/src-' . bin2hex(random_bytes(3)) . '.jpg';
        imagejpeg($img, $path, 90);
        if ($orientation !== null) {
            // Insert an APP1 Exif segment with only the Orientation tag (big-endian TIFF).
            $tiff = "MM\x00\x2A\x00\x00\x00\x08" . "\x00\x01" . "\x01\x12\x00\x03\x00\x00\x00\x01"
                . pack('n', $orientation) . "\x00\x00" . "\x00\x00\x00\x00";
            $app1 = "Exif\x00\x00" . $tiff;
            $data = (string) file_get_contents($path);
            file_put_contents($path, "\xFF\xD8\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1 . substr($data, 2));
        }
        return $path;
    }

    public function testLargePhotoProducesThreeSizes(): void
    {
        $result = (new Images($this->dir))->store($this->makeJpeg(4000, 3000), 'holiday photo.JPG');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{12}\.jpg$/', $result['filename']);
        $this->assertSame([2000, 1500], [$result['width'], $result['height']]);
        $this->assertSame('image/jpeg', $result['mime']);
        $this->assertSame('holiday photo.JPG', $result['original_name']);

        $base = substr($result['filename'], 0, 12);
        $this->assertSame([2000, 1500], array_slice(getimagesize($this->dir . "/$base.jpg"), 0, 2));
        $this->assertSame([1000, 750], array_slice(getimagesize($this->dir . "/$base-m.jpg"), 0, 2));
        $this->assertSame([400, 300], array_slice(getimagesize($this->dir . "/$base-s.jpg"), 0, 2));
    }

    public function testTallImageLimitsLongSide(): void
    {
        $result = (new Images($this->dir))->store($this->makeJpeg(1000, 4000), 'tall.jpg');
        $this->assertSame([500, 2000], [$result['width'], $result['height']]);
    }

    public function testSmallImageIsNotEnlarged(): void
    {
        $result = (new Images($this->dir))->store($this->makeJpeg(300, 200), 'small.jpg');
        $base = substr($result['filename'], 0, 12);
        $this->assertSame([300, 200], array_slice(getimagesize($this->dir . "/$base-m.jpg"), 0, 2));
        $this->assertSame([300, 200], array_slice(getimagesize($this->dir . "/$base-s.jpg"), 0, 2));
    }

    public function testPngKeepsTransparency(): void
    {
        $img = imagecreatetruecolor(50, 50);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        $path = $this->dir . '/t.png';
        imagepng($img, $path);

        $result = (new Images($this->dir))->store($path, 't.png');
        $this->assertStringEndsWith('.png', $result['filename']);
        $out = imagecreatefrompng($this->dir . '/' . $result['filename']);
        $this->assertSame(127, (imagecolorat($out, 10, 10) >> 24) & 0x7F);
    }

    public function testGifAndWebpBecomeJpeg(): void
    {
        $img = imagecreatetruecolor(20, 10);
        imagegif($img, $this->dir . '/a.gif');
        imagewebp($img, $this->dir . '/a.webp');
        $images = new Images($this->dir);
        $this->assertSame('image/jpeg', $images->store($this->dir . '/a.gif', 'a.gif')['mime']);
        $this->assertSame('image/jpeg', $images->store($this->dir . '/a.webp', 'a.webp')['mime']);
    }

    public function testPhpFileWithImageExtensionIsRejected(): void
    {
        $path = $this->dir . '/evil.jpg';
        file_put_contents($path, "<?php echo 'hacked'; ?>");
        $this->expectException(ImageException::class);
        (new Images($this->dir))->store($path, 'evil.jpg');
    }

    public function testImageWithAppendedPhpIsCleaned(): void
    {
        $path = $this->makeJpeg(100, 100);
        file_put_contents($path, "<?php system(\$_GET['c']); ?>", FILE_APPEND);
        $result = (new Images($this->dir))->store($path, 'x.jpg');
        $this->assertStringNotContainsString('<?php', (string) file_get_contents($this->dir . '/' . $result['filename']));
    }

    /** @return iterable<array{int, int, int, bool}> orientation, width, height, red marker at top-left */
    public static function orientations(): iterable
    {
        // Source is 400x200 with red in the top-left. After correcting the orientation:
        yield 'upright' => [1, 400, 200, 'tl'];
        yield 'mirrored' => [2, 400, 200, 'tr'];
        yield 'upside down' => [3, 400, 200, 'br'];
        yield 'flipped' => [4, 400, 200, 'bl'];
        yield 'transpose' => [5, 200, 400, 'tl'];
        yield 'rotate 90 cw' => [6, 200, 400, 'tr'];
        yield 'transverse' => [7, 200, 400, 'br'];
        yield 'rotate 90 ccw' => [8, 200, 400, 'bl'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('orientations')]
    public function testExifOrientationIsApplied(int $orientation, int $w, int $h, string $red): void
    {
        $path = $this->makeJpeg(400, 200, $orientation);
        $this->assertSame($orientation, Images::jpegOrientation($path));
        $result = (new Images($this->dir))->store($path, 'o.jpg');
        $this->assertSame([$w, $h], [$result['width'], $result['height']]);

        $img = imagecreatefromjpeg($this->dir . '/' . $result['filename']);
        $corners = ['tl' => [5, 5], 'tr' => [$w - 6, 5], 'bl' => [5, $h - 6], 'br' => [$w - 6, $h - 6]];
        foreach ($corners as $name => [$x, $y]) {
            $isRed = ((imagecolorat($img, $x, $y) >> 16) & 0xFF) > 200;
            $this->assertSame($name === $red, $isRed, "corner $name");
        }
    }

    public function testIniBytes(): void
    {
        $this->assertSame(2 * 1024 * 1024, Images::iniBytes('2M'));
        $this->assertSame(1024, Images::iniBytes('1k'));
        $this->assertSame(0, Images::iniBytes(''));
    }
}
