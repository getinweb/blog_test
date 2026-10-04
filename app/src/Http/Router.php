<?php

declare(strict_types=1);

namespace App\Http;

use App\View\TemplateRendererInterface;

final readonly class Router implements RequestHandlerInterface
{
    /** @param array<string, RequestHandlerInterface> $routes */
    public function __construct(private array $routes, private TemplateRendererInterface $renderer)
    {
    }

    public function handle(Request $request): Response
    {
        if (!isset($this->routes[$request->path])) {
            return new Response($this->renderer->render('error.tpl', [
                'title' => 'Страница не найдена',
                'message' => 'Проверьте адрес или вернитесь на главную.',
            ]), 404);
        }

        if (!in_array($request->method, ['GET', 'HEAD'], true)) {
            return new Response($this->renderer->render('error.tpl', [
                'title' => 'Метод не поддерживается',
                'message' => 'Эта страница доступна только для просмотра.',
            ]), 405, ['Allow' => 'GET, HEAD']);
        }

        return $this->routes[$request->path]->handle($request);
    }
}
