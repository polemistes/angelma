<?php

declare(strict_types=1);

namespace Angelma\Tests;

use Angelma\Controllers\BlogController;
use PHPUnit\Framework\TestCase;

final class CommentRulesTest extends TestCase
{
    public function testFormMustBeAtLeastThreeSecondsOld(): void
    {
        $t = 1_800_000_000;
        $stamp = BlogController::formTime($t);
        $this->assertFalse(BlogController::formAgeOk($stamp, $t + 2));
        $this->assertTrue(BlogController::formAgeOk($stamp, $t + 3));
        $this->assertFalse(BlogController::formAgeOk($stamp, $t + 90000));
    }

    public function testForgedTimestampIsRejected(): void
    {
        $t = 1_800_000_000;
        $forged = ($t - 100) . '.' . substr(BlogController::formTime($t), -16);
        $this->assertFalse(BlogController::formAgeOk($forged, $t));
        $this->assertFalse(BlogController::formAgeOk('', $t));
        $this->assertFalse(BlogController::formAgeOk('abc', $t));
    }

    public function testCountUrls(): void
    {
        $this->assertSame(0, BlogController::countUrls('No links here.'));
        $this->assertSame(2, BlogController::countUrls('See https://a.example and www.b.example'));
        $this->assertSame(3, BlogController::countUrls('http://a.b http://c.d HTTPS://E.F'));
    }
}
