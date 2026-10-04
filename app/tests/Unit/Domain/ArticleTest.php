<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Article;
use App\Domain\Category;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ArticleTest extends TestCase
{
    public function testArticleKeepsItsCategoriesAndPublicationInstant(): void
    {
        $categories = [new Category(1, 'PHP', ''), new Category(2, 'MySQL', '')];
        $publishedAt = new DateTimeImmutable('2026-10-04T12:00:00+03:00');
        $article = new Article(1, 'example.webp', str_repeat('Я', 255), '', 'Текст', $publishedAt, 0, $categories);

        self::assertSame($categories, $article->categories);
        self::assertSame($publishedAt, $article->publishedAt);
        self::assertSame(0, $article->views);
    }

    #[DataProvider('invalidArticles')]
    public function testInvalidArticleIsRejected(int $id, string $imagePath, string $title, string $text, int $views): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Article($id, $imagePath, $title, '', $text, new DateTimeImmutable(), $views, [new Category(1, 'PHP', '')]);
    }

    public function testAtLeastOneCategoryIsRequired(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Article(1, 'a.webp', 'PHP', '', 'Текст', new DateTimeImmutable(), 0, []);
    }

    public function testDuplicateCategoryIdsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Article(1, 'a.webp', 'PHP', '', 'Текст', new DateTimeImmutable(), 0, [
            new Category(1, 'PHP', ''),
            new Category(1, 'PHP duplicate', ''),
        ]);
    }

    /** @return iterable<string, array{int, string, string, string, int}> */
    public static function invalidArticles(): iterable
    {
        yield 'zero id' => [0, 'a.webp', 'PHP', 'Текст', 0];
        yield 'empty image' => [1, '', 'PHP', 'Текст', 0];
        yield 'long image path' => [1, str_repeat('a', 256), 'PHP', 'Текст', 0];
        yield 'empty title' => [1, 'a.webp', ' ', 'Текст', 0];
        yield 'long title' => [1, 'a.webp', str_repeat('Я', 256), 'Текст', 0];
        yield 'empty text' => [1, 'a.webp', 'PHP', ' ', 0];
        yield 'negative views' => [1, 'a.webp', 'PHP', 'Текст', -1];
    }
}
