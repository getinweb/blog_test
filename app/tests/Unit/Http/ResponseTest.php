<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testAdditionalHeadersKeepTheDefaultContentType(): void
    {
        $response = new Response('Method not allowed', 405, ['Allow' => 'GET, HEAD']);

        self::assertSame('text/html; charset=UTF-8', $response->headers['Content-Type']);
        self::assertSame('GET, HEAD', $response->headers['Allow']);
    }

    public function testContentTypeCanBeOverridden(): void
    {
        $response = new Response('Error', 500, ['Content-Type' => 'text/plain; charset=UTF-8']);

        self::assertSame(['Content-Type' => 'text/plain; charset=UTF-8'], $response->headers);
    }
}
