<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\RequestHandlerInterface;
use App\Http\Response;
use App\Repository\ArticleRepositoryInterface;
use App\Repository\CategoryRepositoryInterface;
use App\View\TemplateRendererInterface;

final readonly class HomeController implements RequestHandlerInterface
{
    public function __construct(
        private TemplateRendererInterface $renderer,
        private CategoryRepositoryInterface $categories,
        private ArticleRepositoryInterface $articles,
    ) {
    }

    public function handle(Request $request): Response
    {
        $categories = $this->categories->findWithArticles();
        $latestByCategory = $categories === []
            ? []
            : $this->articles->findLatestByCategories(array_column($categories, 'id'), 3);
        $sections = [];

        foreach ($categories as $category) {
            $latest = $latestByCategory[$category->id] ?? [];

            if ($latest !== []) {
                $sections[] = ['category' => $category, 'articles' => $latest];
            }
        }

        return new Response($this->renderer->render('home.tpl', ['title' => 'Статьи', 'sections' => $sections]));
    }
}
