<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Request;
use App\Http\RequestHandlerInterface;
use App\Http\Response;
use App\Http\Router;
use App\View\TemplateRendererInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    #[DataProvider('readMethods')]
    public function testReadRequestsArePassedToTheMatchingHandler(string $method): void
    {
        $request = new Request($method, '/');
        $expected = new Response('Home');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->with($request)->willReturn($expected);
        $renderer = $this->createStub(TemplateRendererInterface::class);

        self::assertSame($expected, (new Router(['/' => $handler], $renderer))->handle($request));
    }

    public function testUnknownPathReturnsNotFound(): void
    {
        $renderer = $this->createStub(TemplateRendererInterface::class);
        $renderer->method('render')->willReturn('Not found');
        $response = (new Router([], $renderer))->handle(new Request('GET', '/missing'));

        self::assertSame(404, $response->status);
        self::assertSame('Not found', $response->body);
    }

    public function testUnsupportedMethodDoesNotCallTheHandler(): void
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');
        $renderer = $this->createStub(TemplateRendererInterface::class);
        $renderer->method('render')->willReturn('Method not allowed');
        $response = (new Router(['/' => $handler], $renderer))->handle(new Request('POST', '/'));

        self::assertSame(405, $response->status);
        self::assertSame('GET, HEAD', $response->headers['Allow']);
    }

    /** @return iterable<string, array{string}> */
    public static function readMethods(): iterable
    {
        yield 'GET' => ['GET'];
        yield 'HEAD' => ['HEAD'];
    }
}
