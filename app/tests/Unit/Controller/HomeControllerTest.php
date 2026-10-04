<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\HomeController;
use App\Domain\Article;
use App\Domain\Category;
use App\Http\Request;
use App\Repository\ArticleRepositoryInterface;
use App\Repository\CategoryRepositoryInterface;
use App\View\TemplateRendererInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class HomeControllerTest extends TestCase
{
    public function testLatestArticlesAreRequestedForAllCategoriesTogether(): void
    {
        $php = new Category(1, 'PHP', 'Язык');
        $mysql = new Category(2, 'MySQL', 'База данных');
        $article = new Article(5, 'images/test.png', 'Статья', 'Описание', 'Текст', new DateTimeImmutable(), 10, [$php, $mysql]);
        $categories = $this->createMock(CategoryRepositoryInterface::class);
        $categories->expects($this->once())->method('findWithArticles')->willReturn([$php, $mysql]);
        $articles = $this->createMock(ArticleRepositoryInterface::class);
        $articles->expects($this->once())->method('findLatestByCategories')->with([1, 2], 3)->willReturn([
            1 => [$article], 2 => [$article],
        ]);
        $articles->expects($this->never())->method('incrementViews');
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer->expects($this->once())->method('render')->with('home.tpl', [
            'title' => 'Статьи',
            'sections' => [
                ['category' => $php, 'articles' => [$article]],
                ['category' => $mysql, 'articles' => [$article]],
            ],
        ])->willReturn('Rendered homepage');

        $response = (new HomeController($renderer, $categories, $articles))->handle(new Request('GET', '/'));

        self::assertSame(200, $response->status);
        self::assertSame('Rendered homepage', $response->body);
    }

    public function testEmptyBlogDoesNotRequestArticles(): void
    {
        $categories = $this->createStub(CategoryRepositoryInterface::class);
        $categories->method('findWithArticles')->willReturn([]);
        $articles = $this->createMock(ArticleRepositoryInterface::class);
        $articles->expects($this->never())->method('findLatestByCategories');
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer->expects($this->once())->method('render')->with('home.tpl', [
            'title' => 'Статьи', 'sections' => [],
        ])->willReturn('Empty blog');

        self::assertSame('Empty blog', (new HomeController($renderer, $categories, $articles))->handle(new Request('GET', '/'))->body);
    }

    public function testCategoryThatBecomesEmptyBetweenQueriesIsOmitted(): void
    {
        $category = new Category(1, 'PHP', '');
        $categories = $this->createStub(CategoryRepositoryInterface::class);
        $categories->method('findWithArticles')->willReturn([$category]);
        $articles = $this->createStub(ArticleRepositoryInterface::class);
        $articles->method('findLatestByCategories')->willReturn([1 => []]);
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer->expects($this->once())->method('render')->with('home.tpl', [
            'title' => 'Статьи', 'sections' => [],
        ])->willReturn('Empty blog');

        self::assertSame('Empty blog', (new HomeController($renderer, $categories, $articles))->handle(new Request('GET', '/'))->body);
    }
}
