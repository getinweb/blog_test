<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Http\Request;
use App\Http\RequestHandlerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    public function testHomePageUsesTheSharedLayoutAndCompiledStyles(): void
    {
        $response = $this->application()->handle(Request::fromServer([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/?page=1',
        ], ['page' => '1']));

        self::assertSame(200, $response->status);
        self::assertSame('text/html; charset=UTF-8', $response->headers['Content-Type']);
        self::assertStringContainsString('<html lang="ru">', $response->body);
        self::assertStringContainsString('href="/assets/css/app.css"', $response->body);
        self::assertStringContainsString('Статей пока нет.', $response->body);
        self::assertStringNotContainsString('<script', $response->body);
    }

    #[DataProvider('errorRequests')]
    public function testRoutingErrorsUseHtmlPages(string $method, string $path, int $status, string $message): void
    {
        $response = $this->application()->handle(new Request($method, $path));

        self::assertSame($status, $response->status);
        self::assertStringContainsString('<html lang="ru">', $response->body);
        self::assertStringContainsString($message, $response->body);

        if ($status === 405) {
            self::assertSame('GET, HEAD', $response->headers['Allow']);
        }
    }

    #[DataProvider('headRequests')]
    public function testHeadResponsesHaveNoBody(string $path, int $status): void
    {
        $response = $this->application()->handle(new Request('HEAD', $path));

        self::assertSame($status, $response->status);
        self::assertSame('', $response->body);
    }

    /** @return iterable<string, array{string, string, int, string}> */
    public static function errorRequests(): iterable
    {
        yield 'missing page' => ['GET', '/missing', 404, 'Страница не найдена'];
        yield 'unsupported method' => ['POST', '/', 405, 'Метод не поддерживается'];
        yield 'missing page with unsupported method' => ['POST', '/missing', 404, 'Страница не найдена'];
    }

    /** @return iterable<string, array{string, int}> */
    public static function headRequests(): iterable
    {
        yield 'home' => ['/', 200];
        yield 'missing page' => ['/missing', 404];
    }

    private function application(): RequestHandlerInterface
    {
        $factory = require dirname(__DIR__, 2) . '/config/container.php';

        return $factory(getenv())->get(RequestHandlerInterface::class);
    }
}
