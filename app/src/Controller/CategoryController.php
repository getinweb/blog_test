<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\ArticleSort;
use App\Http\Request;
use App\Http\RequestHandlerInterface;
use App\Http\Response;
use App\Repository\ArticleRepositoryInterface;
use App\Repository\CategoryRepositoryInterface;
use App\View\TemplateRendererInterface;

final readonly class CategoryController implements RequestHandlerInterface
{
    public function __construct(
        private TemplateRendererInterface $renderer,
        private CategoryRepositoryInterface $categories,
        private ArticleRepositoryInterface $articles,
        private int $articlesPerPage,
    ) {
    }

    public function handle(Request $request): Response
    {
        $categoryId = $this->positiveInteger($request->query['id'] ?? null);
        $page = $this->positiveInteger($request->query['page'] ?? '1');
        $sortValue = $request->query['sort'] ?? ArticleSort::Newest->value;
        $sort = is_string($sortValue) ? ArticleSort::tryFrom($sortValue) : null;

        if ($categoryId === null || $page === null || $sort === null) {
            return $this->error(400, 'Некорректный запрос', 'Проверьте категорию, номер страницы и сортировку в адресе.');
        }

        $category = $this->categories->findById($categoryId);

        if ($category === null) {
            return $this->error(404, 'Категория не найдена', 'Проверьте адрес или выберите категорию на главной.');
        }

        $totalArticles = $this->articles->countByCategory($categoryId);
        $totalPages = $totalArticles === 0 ? 1 : intdiv($totalArticles - 1, $this->articlesPerPage) + 1;

        if ($page > $totalPages) {
            return $this->error(404, 'Страница не найдена', 'В этой категории нет такой страницы со статьями.');
        }

        $articles = $totalArticles === 0 ? [] : $this->articles->findByCategory(
            $categoryId,
            $sort,
            $this->articlesPerPage,
            ($page - 1) * $this->articlesPerPage,
        );

        return new Response($this->renderer->render('category.tpl', [
            'title' => $category->name,
            'category' => $category,
            'articles' => $articles,
            'sort' => $sort->value,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalArticles' => $totalArticles,
        ]));
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (!is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $integer === false ? null : $integer;
    }

    private function error(int $status, string $title, string $message): Response
    {
        return new Response($this->renderer->render('error.tpl', ['title' => $title, 'message' => $message]), $status);
    }
}
