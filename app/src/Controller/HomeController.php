<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\RequestHandlerInterface;
use App\Http\Response;
use App\View\TemplateRendererInterface;

final readonly class HomeController implements RequestHandlerInterface
{
    public function __construct(private TemplateRendererInterface $renderer)
    {
    }

    public function handle(Request $request): Response
    {
        return new Response($this->renderer->render('home.tpl', ['title' => 'Статьи']));
    }
}
