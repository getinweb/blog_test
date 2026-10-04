<?php

declare(strict_types=1);

namespace App\Http;

final readonly class Request
{
    /** @param array<array-key, mixed> $query */
    public function __construct(public string $method, public string $path, public array $query = [])
    {
    }

    /**
     * @param array{REQUEST_METHOD?: string, REQUEST_URI?: string} $server
     * @param array<array-key, mixed> $query
     */
    public static function fromServer(array $server, array $query = []): self
    {
        return new self(
            $server['REQUEST_METHOD'] ?? 'GET',
            explode('?', $server['REQUEST_URI'] ?? '/', 2)[0],
            $query,
        );
    }
}
