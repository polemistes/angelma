<?php

declare(strict_types=1);

namespace Angelma\Tests;

use Angelma\App;
use Angelma\Request;

final class AppTest extends DbTestCase
{
    protected function tearDown(): void
    {
        App::$config = [];
        App::$request = null;
        parent::tearDown();
    }

    public function testProductionErrorPageHidesDetails(): void
    {
        App::$config = ['debug' => false];
        App::$request = new Request('GET', '/x');
        $error = new \PDOException('SQLSTATE[HY000]: no such table: secret_table in /var/www/app/Repos/Pages.php');
        $response = App::errorResponse(500, $error);
        $this->assertSame(500, $response->status);
        $this->assertStringContainsString(t('error_500_title'), $response->body);
        $this->assertStringNotContainsString('secret_table', $response->body);
        $this->assertStringNotContainsString('/var/www', $response->body);
        $this->assertStringNotContainsString('SQLSTATE', $response->body);
    }

    public function testDebugErrorPageShowsTheException(): void
    {
        App::$config = ['debug' => true];
        $response = App::errorResponse(500, new \RuntimeException('Visible <detail>'));
        $this->assertStringContainsString('Visible &lt;detail&gt;', $response->body);
    }

    public function testEveryResponseGetsTheCsp(): void
    {
        $response = App::handle(new Request('GET', '/install/rewrite-check'));
        $this->assertSame(App::CSP, $response->header('Content-Security-Policy'));
        $this->assertSame('ok', $response->body);
    }
}
