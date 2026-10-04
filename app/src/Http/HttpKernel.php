<?php

declare(strict_types=1);

namespace App\Http;

use Closure;
use Throwable;

final readonly class HttpKernel implements RequestHandlerInterface
{
    /** @param Closure(Throwable): void $logError */
    public function __construct(private RequestHandlerInterface $router, private Closure $logError)
    {
    }

    public function handle(Request $request): Response
    {
        try {
            $response = $this->router->handle($request);
        } catch (Throwable $exception) {
            ($this->logError)($exception);
            $response = new Response('Внутренняя ошибка сервера.', 500, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        return $request->method === 'HEAD'
            ? new Response('', $response->status, $response->headers)
            : $response;
    }
}
