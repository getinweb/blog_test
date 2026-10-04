<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Article\ArticleViewCounterInterface;
use App\Controller\ArticleController;
use App\Domain\Article;
use App\Domain\Category;
use App\Http\Request;
use App\Repository\ArticleRepositoryInterface;
use App\View\TemplateRendererInterface;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ArticleControllerTest extends TestCase
{
    #[DataProvider('visits')]
    public function testGetRendersTheArticleWithTheCurrentVisitIncluded(bool $counted, int $expectedViews): void
    {
        $article = $this->article();
        $related = [$this->article(8)];
        $articles = $this->createMock(ArticleRepositoryInterface::class);
        $articles->expects($this->once())->method('findById')->with(7)->willReturn($article);
        $articles->expects($this->once())->method('findRelated')->with(7, 3)->willReturn($related);
        $views = $this->createMock(ArticleViewCounterInterface::class);
        $views->expects($this->once())->method('record')->with(7)->willReturn($counted);
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer->expects($this->once())->method('render')->with('article.tpl', [
            'title' => $article->title, 'article' => $article, 'views' => $expectedViews, 'relatedArticles' => $related,
        ])->willReturn('Article page');

        $response = (new ArticleController($renderer, $articles, $views))->handle(new Request('GET', '/article', ['id' => '7']));

        self::assertSame(200, $response->status);
        self::assertSame('Article page', $response->body);
        self::assertSame('private, no-store', $response->headers['Cache-Control']);
    }

    public function testHeadRendersWithoutRecordingAView(): void
    {
        $article = $this->article();
        $articles = $this->createStub(ArticleRepositoryInterface::class);
        $articles->method('findById')->willReturn($article);
        $views = $this->createMock(ArticleViewCounterInterface::class);
        $views->expects($this->never())->method('record');
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer->expects($this->once())->method('render')->with('article.tpl', [
            'title' => $article->title, 'article' => $article, 'views' => 5, 'relatedArticles' => [],
        ])->willReturn('Article page');

        self::assertSame(200, (new ArticleController($renderer, $articles, $views))->handle(
            new Request('HEAD', '/article', ['id' => '7']),
        )->status);
    }

    /** @param array<string, mixed> $query */
    #[DataProvider('invalidQueries')]
    public function testInvalidIdReturnsBadRequestWithoutReadingOrCounting(array $query): void
    {
        $articles = $this->createMock(ArticleRepositoryInterface::class);
        $articles->expects($this->never())->method('findById');
        $articles->expects($this->never())->method('findRelated');
        $views = $this->createMock(ArticleViewCounterInterface::class);
        $views->expects($this->never())->method('record');
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer->expects($this->once())->method('render')->with('error.tpl', $this->anything())->willReturn('Invalid id');

        $response = (new ArticleController($renderer, $articles, $views))->handle(new Request('GET', '/article', $query));

        self::assertSame(400, $response->status);
        self::assertSame('Invalid id', $response->body);
    }

    public function testMissingArticleReturnsNotFoundWithoutRecordingAView(): void
    {
        $articles = $this->createMock(ArticleRepositoryInterface::class);
        $articles->expects($this->once())->method('findById')->with(999)->willReturn(null);
        $articles->expects($this->never())->method('findRelated');
        $views = $this->createMock(ArticleViewCounterInterface::class);
        $views->expects($this->never())->method('record');
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer->expects($this->once())->method('render')->with('error.tpl', $this->anything())->willReturn('Not found');

        self::assertSame(404, (new ArticleController($renderer, $articles, $views))->handle(
            new Request('GET', '/article', ['id' => '999']),
        )->status);
    }

    /** @return iterable<string, array{bool, int}> */
    public static function visits(): iterable
    {
        yield 'first visit' => [true, 6];
        yield 'repeat visit' => [false, 5];
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidQueries(): iterable
    {
        yield 'missing' => [[]];
        yield 'empty' => [['id' => '']];
        yield 'zero' => [['id' => '0']];
        yield 'negative' => [['id' => '-1']];
        yield 'array' => [['id' => ['7']]];
        yield 'fraction' => [['id' => '7.5']];
        yield 'SQL' => [['id' => '7 OR 1=1']];
        yield 'overflow' => [['id' => PHP_INT_MAX . '0']];
        yield 'whitespace' => [['id' => ' 7']];
        yield 'newline' => [['id' => "7\n"]];
    }

    private function article(int $id = 7): Article
    {
        return new Article($id, 'images/a.png', 'Заголовок', 'Описание', 'Текст', new DateTimeImmutable(), 5, [new Category(1, 'PHP', '')]);
    }
}
