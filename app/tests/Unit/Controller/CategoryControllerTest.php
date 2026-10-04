<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\CategoryController;
use App\Domain\Article;
use App\Domain\ArticleSort;
use App\Domain\Category;
use App\Http\Request;
use App\Repository\ArticleRepositoryInterface;
use App\Repository\CategoryRepositoryInterface;
use App\View\TemplateRendererInterface;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CategoryControllerTest extends TestCase
{
    /** @param array<string, string> $query */
    #[DataProvider('validQueries')]
    public function testConfiguredPageSizeAndSelectedSortArePassedToTheRepository(array $query, ArticleSort $sort, int $page): void
    {
        $category = new Category(7, 'PHP', 'Описание');
        $article = new Article(1, 'images/a.png', 'Статья', '', 'Текст', new DateTimeImmutable(), 5, [$category]);
        $categories = $this->createMock(CategoryRepositoryInterface::class);
        $categories->expects($this->once())->method('findById')->with(7)->willReturn($category);
        $articles = $this->createMock(ArticleRepositoryInterface::class);
        $articles->expects($this->once())->method('countByCategory')->with(7)->willReturn(7);
        $articles->expects($this->once())->method('findByCategory')->with(7, $sort, 3, ($page - 1) * 3)->willReturn([$article]);
        $articles->expects($this->never())->method('incrementViews');
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer->expects($this->once())->method('render')->with('category.tpl', [
            'title' => 'PHP', 'category' => $category, 'articles' => [$article],
            'sort' => $sort->value, 'page' => $page, 'totalPages' => 3, 'totalArticles' => 7,
        ])->willReturn('Category page');

        $response = (new CategoryController($renderer, $categories, $articles, 3))->handle(
            new Request('GET', '/category', ['id' => '7'] + $query),
        );

        self::assertSame(200, $response->status);
        self::assertSame('Category page', $response->body);
    }

    /** @param array<string, mixed> $query */
    #[DataProvider('invalidQueries')]
    public function testInvalidQueryReturnsBadRequestWithoutCallingRepositories(array $query): void
    {
        $categories = $this->createMock(CategoryRepositoryInterface::class);
        $categories->expects($this->never())->method('findById');
        $articles = $this->createMock(ArticleRepositoryInterface::class);
        $articles->expects($this->never())->method('countByCategory');
        $articles->expects($this->never())->method('findByCategory');
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer->expects($this->once())->method('render')->with('error.tpl', $this->anything())->willReturn('Invalid query');

        $response = (new CategoryController($renderer, $categories, $articles, 12))->handle(new Request('GET', '/category', $query));

        self::assertSame(400, $response->status);
        self::assertSame('Invalid query', $response->body);
    }

    public function testMissingCategoryReturnsNotFound(): void
    {
        $categories = $this->createStub(CategoryRepositoryInterface::class);
        $categories->method('findById')->willReturn(null);
        $articles = $this->createMock(ArticleRepositoryInterface::class);
        $articles->expects($this->never())->method('countByCategory');
        $articles->expects($this->never())->method('findByCategory');
        $renderer = $this->createStub(TemplateRendererInterface::class);
        $renderer->method('render')->willReturn('Not found');

        self::assertSame(404, (new CategoryController($renderer, $categories, $articles, 12))->handle(
            new Request('GET', '/category', ['id' => '999']),
        )->status);
    }

    public function testEmptyCategoryHasOneEmptyPage(): void
    {
        $category = new Category(7, 'Пустая', '');
        $categories = $this->createStub(CategoryRepositoryInterface::class);
        $categories->method('findById')->willReturn($category);
        $articles = $this->createMock(ArticleRepositoryInterface::class);
        $articles->expects($this->once())->method('countByCategory')->willReturn(0);
        $articles->expects($this->never())->method('findByCategory');
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer->expects($this->once())->method('render')->with('category.tpl', [
            'title' => 'Пустая', 'category' => $category, 'articles' => [],
            'sort' => 'date', 'page' => 1, 'totalPages' => 1, 'totalArticles' => 0,
        ])->willReturn('Empty category');

        self::assertSame(200, (new CategoryController($renderer, $categories, $articles, 12))->handle(
            new Request('GET', '/category', ['id' => '7']),
        )->status);
    }

    #[DataProvider('outOfRangePages')]
    public function testOutOfRangePageReturnsNotFoundBeforeCalculatingOffset(int $total, string $page): void
    {
        $categories = $this->createStub(CategoryRepositoryInterface::class);
        $categories->method('findById')->willReturn(new Category(7, 'PHP', ''));
        $articles = $this->createMock(ArticleRepositoryInterface::class);
        $articles->expects($this->once())->method('countByCategory')->willReturn($total);
        $articles->expects($this->never())->method('findByCategory');
        $renderer = $this->createStub(TemplateRendererInterface::class);
        $renderer->method('render')->willReturn('Not found');

        self::assertSame(404, (new CategoryController($renderer, $categories, $articles, 12))->handle(
            new Request('GET', '/category', ['id' => '7', 'page' => $page]),
        )->status);
    }

    /** @return iterable<string, array{array<string, string>, ArticleSort, int}> */
    public static function validQueries(): iterable
    {
        yield 'defaults' => [[], ArticleSort::Newest, 1];
        yield 'newest second page' => [['sort' => 'date', 'page' => '2'], ArticleSort::Newest, 2];
        yield 'most viewed last page' => [['sort' => 'views', 'page' => '3'], ArticleSort::MostViewed, 3];
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidQueries(): iterable
    {
        yield 'missing id' => [[]];
        yield 'empty id' => [['id' => '']];
        yield 'zero id' => [['id' => '0']];
        yield 'negative id' => [['id' => '-1']];
        yield 'array id' => [['id' => ['1']]];
        yield 'fractional id' => [['id' => '1.5']];
        yield 'SQL in id' => [['id' => '1 OR 1=1']];
        yield 'overflowing id' => [['id' => PHP_INT_MAX . '0']];
        yield 'zero page' => [['id' => '1', 'page' => '0']];
        yield 'negative page' => [['id' => '1', 'page' => '-1']];
        yield 'array page' => [['id' => '1', 'page' => ['2']]];
        yield 'empty page' => [['id' => '1', 'page' => '']];
        yield 'scientific page' => [['id' => '1', 'page' => '1e2']];
        yield 'overflowing page' => [['id' => '1', 'page' => PHP_INT_MAX . '0']];
        yield 'unknown sort' => [['id' => '1', 'sort' => 'title']];
        yield 'array sort' => [['id' => '1', 'sort' => ['views']]];
        yield 'empty sort' => [['id' => '1', 'sort' => '']];
    }

    /** @return iterable<string, array{int, string}> */
    public static function outOfRangePages(): iterable
    {
        yield 'empty category' => [0, '2'];
        yield 'exact page boundary' => [12, '2'];
        yield 'after partial last page' => [13, '3'];
        yield 'offset would overflow' => [13, (string) PHP_INT_MAX];
    }
}
