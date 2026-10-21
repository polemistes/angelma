<?php

declare(strict_types=1);

namespace Angelma\Tests;

use Angelma\HttpException;
use Angelma\Request;
use Angelma\Response;
use Angelma\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private function router(bool $csrfOk = true): Router
    {
        $r = new Router(fn (Request $req): bool => $csrfOk);
        $r->get('/', fn (Request $req): Response => new Response('home'));
        $r->get('/admin/pages/{id}', fn (Request $req, int $id): Response => new Response('page ' . $id));
        $r->post('/admin/pages/{id}', fn (Request $req, int $id): Response => new Response('saved ' . $id));
        $r->get('/blog/{blog}/{post}', fn (Request $req, string $b, string $p): Response => new Response("$b/$p"));
        $r->get('/{slug}', fn (Request $req, string $slug): Response => new Response('slug ' . $slug));
        return $r;
    }

    public function testMatchesStaticAndParameterRoutes(): void
    {
        $r = $this->router();
        $this->assertSame('home', $r->dispatch(new Request('GET', '/'))->body);
        $this->assertSame('page 12', $r->dispatch(new Request('GET', '/admin/pages/12'))->body);
        $this->assertSame('news/hello', $r->dispatch(new Request('GET', '/blog/news/hello'))->body);
        $this->assertSame('slug about', $r->dispatch(new Request('GET', '/about'))->body);
    }

    public function testIdParameterIsAnInteger(): void
    {
        [, $params] = $this->router()->match('GET', '/admin/pages/7');
        $this->assertSame([7], $params);
    }

    public function testIdMustBeNumeric(): void
    {
        $this->assertSame(404, $this->router()->match('GET', '/admin/pages/abc'));
    }

    public function testTrailingSlashIsIgnored(): void
    {
        $this->assertSame('page 3', $this->router()->dispatch(new Request('GET', Request::normalizePath('/admin/pages/3/')))->body);
    }

    public function testUnknownRouteIs404(): void
    {
        $this->assertSame(404, $this->router()->match('GET', '/a/b/c/d'));
        $this->expectException(HttpException::class);
        $this->router()->dispatch(new Request('GET', '/a/b/c/d'));
    }

    public function testWrongMethodIs405(): void
    {
        $this->assertSame(405, $this->router()->match('DELETE', '/admin/pages/1'));
    }

    public function testHeadIsTreatedAsGet(): void
    {
        $this->assertIsArray($this->router()->match('HEAD', '/'));
    }

    public function testPostWithoutValidCsrfIs403(): void
    {
        try {
            $this->router(false)->dispatch(new Request('POST', '/admin/pages/1'));
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->status);
        }
        $this->assertSame('saved 1', $this->router(true)->dispatch(new Request('POST', '/admin/pages/1'))->body);
    }
}
