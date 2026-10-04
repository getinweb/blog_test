<?php

declare(strict_types=1);

namespace App\Controller;

use App\Article\ArticleViewCounterInterface;
use App\Http\Request;
use App\Http\RequestHandlerInterface;
use App\Http\Response;
use App\Repository\ArticleRepositoryInterface;
use App\View\TemplateRendererInterface;

final readonly class ArticleController implements RequestHandlerInterface
{
    public function __construct(
        private TemplateRendererInterface $renderer,
        private ArticleRepositoryInterface $articles,
        private ArticleViewCounterInterface $views,
    ) {
    }

    public function handle(Request $request): Response
    {
        $value = $request->query['id'] ?? null;
        $id = is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value) === 1
            ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;

        if ($id === false) {
            return new Response($this->renderer->render('error.tpl', [
                'title' => 'Некорректный запрос',
                'message' => 'Проверьте идентификатор статьи в адресе.',
            ]), 400);
        }

        $article = $this->articles->findById($id);

        if ($article === null) {
            return new Response($this->renderer->render('error.tpl', [
                'title' => 'Статья не найдена',
                'message' => 'Проверьте адрес или выберите статью на главной.',
            ]), 404);
        }

        $counted = $request->method === 'GET' && $this->views->record($article->id);

        return new Response($this->renderer->render('article.tpl', [
            'title' => $article->title,
            'article' => $article,
            'views' => $article->views + ($counted ? 1 : 0),
        ]), 200, ['Cache-Control' => 'private, no-store']);
    }
}
