<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\HttpKernel;
use App\Http\Request;
use App\Http\RequestHandlerInterface;
use App\Http\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class HttpKernelTest extends TestCase
{
    public function testSuccessfulResponseIsPassedThrough(): void
    {
        $expected = new Response('Home');
        $router = $this->createStub(RequestHandlerInterface::class);
        $router->method('handle')->willReturn($expected);
        $kernel = new HttpKernel($router, static function (Throwable $error): void {
            self::fail('Unexpected error: ' . $error->getMessage());
        });

        self::assertSame($expected, $kernel->handle(new Request('GET', '/')));
    }

    public function testHeadPreservesStatusAndHeadersButHasNoBody(): void
    {
        $router = $this->createStub(RequestHandlerInterface::class);
        $router->method('handle')->willReturn(new Response('Not found', 404, ['X-Test' => 'value']));
        $kernel = new HttpKernel($router, static function (Throwable $error): void {
            self::fail('Unexpected error: ' . $error->getMessage());
        });
        $response = $kernel->handle(new Request('HEAD', '/missing'));

        self::assertSame(404, $response->status);
        self::assertSame('value', $response->headers['X-Test']);
        self::assertSame('', $response->body);
    }

    #[DataProvider('methods')]
    public function testExceptionsAreLoggedAndNotExposedInTheResponse(string $method): void
    {
        $exception = new RuntimeException('secret database password');
        $logged = null;
        $router = $this->createStub(RequestHandlerInterface::class);
        $router->method('handle')->willThrowException($exception);
        $kernel = new HttpKernel($router, static function (Throwable $error) use (&$logged): void {
            $logged = $error;
        });
        $response = $kernel->handle(new Request($method, '/'));

        self::assertSame($exception, $logged);
        self::assertSame(500, $response->status);
        self::assertSame('text/plain; charset=UTF-8', $response->headers['Content-Type']);
        self::assertStringNotContainsString('secret', $response->body);
        self::assertSame($method === 'HEAD' ? '' : 'Внутренняя ошибка сервера.', $response->body);
    }

    /** @return iterable<string, array{string}> */
    public static function methods(): iterable
    {
        yield 'GET' => ['GET'];
        yield 'HEAD' => ['HEAD'];
    }
}
