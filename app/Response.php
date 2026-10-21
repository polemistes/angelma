<?php

declare(strict_types=1);

namespace Angelma;

/**
 * An HTTP response: status, headers and body.
 */
final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = ['Content-Type' => 'text/html; charset=utf-8'],
    ) {
    }

    /** File sent instead of the body; deleted after sending when $deleteFile is set. */
    public ?string $file = null;
    public bool $deleteFile = false;

    /** A file download, streamed from disk. */
    public static function download(string $path, string $filename, string $type, bool $deleteAfter = false): self
    {
        $response = new self('', 200, [
            'Content-Type' => $type,
            'Content-Disposition' => 'attachment; filename="' . str_replace(['"', "\r", "\n"], '', $filename) . '"',
            'Content-Length' => (string) filesize($path),
            'Cache-Control' => 'no-store',
        ]);
        $response->file = $path;
        $response->deleteFile = $deleteAfter;
        return $response;
    }

    public static function redirect(string $location, int $status = 303): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public static function text(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }
        return null;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            header_remove('X-Powered-By');
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        if ($this->file !== null) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            readfile($this->file);
            if ($this->deleteFile) {
                @unlink($this->file);
            }
            return;
        }
        echo $this->body;
    }
}
