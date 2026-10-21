<?php

declare(strict_types=1);

namespace Angelma;

/**
 * Thrown to stop handling a request with an HTTP status (403, 404 ...) or a ready response
 * (for example a redirect to the login page).
 */
final class HttpException extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly ?Response $response = null,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : 'HTTP ' . $status, $status);
    }
}
