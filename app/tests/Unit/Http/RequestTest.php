<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testQueryStringIsSeparatedFromTheRoutePath(): void
    {
        $request = Request::fromServer([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/?page=2&sort=views',
        ], ['page' => '2', 'sort' => 'views']);

        self::assertSame('GET', $request->method);
        self::assertSame('/', $request->path);
        self::assertSame(['page' => '2', 'sort' => 'views'], $request->query);
    }

    public function testPathIsNotDecodedOrTreatedAsAHostName(): void
    {
        $request = Request::fromServer(['REQUEST_URI' => '//example.test/%2F?x=1']);

        self::assertSame('//example.test/%2F', $request->path);
    }
}
