<?php

declare(strict_types=1);

namespace App\Http;

final readonly class Response
{
    /** @var array<string, string> */
    public array $headers;

    /** @param array<string, string> $headers */
    public function __construct(public string $body, public int $status = 200, array $headers = [])
    {
        $this->headers = $headers + ['Content-Type' => 'text/html; charset=UTF-8'];
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->body;
    }
}
