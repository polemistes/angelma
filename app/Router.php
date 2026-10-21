<?php

declare(strict_types=1);

namespace Angelma;

/**
 * Matches method and path patterns such as /admin/pages/{id} to handlers.
 *
 * A handler is either [ControllerClass::class, 'method'] or a callable. It receives the
 * Request followed by the path parameters in order. Parameters named "id" are integers.
 */
final class Router
{
    /** @var list<array{method: string, regex: string, names: list<string>, handler: mixed}> */
    private array $routes = [];

    /**
     * @param (\Closure(Request): bool)|null $csrfCheck verifies the CSRF token of POST requests
     */
    public function __construct(private ?\Closure $csrfCheck = null)
    {
    }

    public function get(string $pattern, mixed $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, mixed $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function add(string $method, string $pattern, mixed $handler): void
    {
        $names = [];
        $regex = preg_replace_callback(
            '/\{([a-z_]+)\}|[^{]+/',
            static function (array $m) use (&$names): string {
                if (isset($m[1]) && $m[1] !== '') {
                    $names[] = $m[1];
                    return $m[1] === 'id' ? '(\d+)' : '([^/]+)';
                }
                return preg_quote($m[0], '#');
            },
            Request::normalizePath($pattern),
        );
        $this->routes[] = [
            'method' => $method,
            'regex' => '#^' . $regex . '$#',
            'names' => $names,
            'handler' => $handler,
        ];
    }

    /**
     * Finds the route for a method and path.
     *
     * @return array{0: mixed, 1: list<int|string>}|int handler and parameters, or 404 / 405
     */
    public function match(string $method, string $path): array|int
    {
        $method = $method === 'HEAD' ? 'GET' : $method;
        $path = Request::normalizePath($path);
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            $pathMatched = true;
            if ($route['method'] !== $method) {
                continue;
            }
            $params = [];
            foreach ($route['names'] as $i => $name) {
                $value = $m[$i + 1];
                $params[] = $name === 'id' ? (int) $value : $value;
            }
            return [$route['handler'], $params];
        }

        return $pathMatched ? 405 : 404;
    }

    public function dispatch(Request $request): Response
    {
        $match = $this->match($request->method, $request->path);
        if (is_int($match)) {
            throw new HttpException($match);
        }

        if ($request->method === 'POST' && $this->csrfCheck !== null && !($this->csrfCheck)($request)) {
            throw new HttpException(403, null, 'Invalid CSRF token');
        }

        [$handler, $params] = $match;
        if (is_array($handler) && is_string($handler[0])) {
            $handler = [new $handler[0](), $handler[1]];
        }

        $response = $handler($request, ...$params);
        return $response instanceof Response ? $response : new Response((string) $response);
    }
}
