<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Database\MigrationRunnerInterface;
use App\Domain\Article;
use App\Domain\ArticleSort;
use App\Domain\Category;
use App\Repository\ArticleRepositoryInterface;
use App\Repository\CategoryRepositoryInterface;
use InvalidArgumentException;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BlogRepositoriesTest extends TestCase
{
    private PDO $connection;
    private CategoryRepositoryInterface $categories;
    private ArticleRepositoryInterface $articles;

    protected function setUp(): void
    {
        $factory = require dirname(__DIR__, 2) . '/config/container.php';
        $container = $factory(getenv());
        $this->connection = $container->get(PDO::class);
        $statement = $this->connection->query('SELECT DATABASE()');
        self::assertInstanceOf(PDOStatement::class, $statement);
        self::assertSame('blog_test', $statement->fetchColumn());
        $container->get(MigrationRunnerInterface::class)->migrate();

        // Only fixture data in the isolated test database; each test rolls it back.
        $this->connection->beginTransaction();
        $this->connection->exec('DELETE FROM article_category');
        $this->connection->exec('DELETE FROM articles');
        $this->connection->exec('DELETE FROM categories');
        $this->seed();
        $this->categories = $container->get(CategoryRepositoryInterface::class);
        $this->articles = $container->get(ArticleRepositoryInterface::class);
    }

    protected function tearDown(): void
    {
        if (isset($this->connection) && $this->connection->inTransaction()) {
            $this->connection->rollBack();
        }
    }

    public function testCategoryLookupIncludesEmptyCategoriesAndPreservesText(): void
    {
        $category = $this->categories->findById(1);

        self::assertInstanceOf(Category::class, $category);
        self::assertSame(1, $category->id);
        self::assertSame('PHP 🐘', $category->name);
        self::assertSame('<b>Описание</b>', $category->description);
        self::assertSame('Empty', $this->categories->findById(3)?->name);
        self::assertNull($this->categories->findById(999));
    }

    public function testOnlyCategoriesWithArticlesAreReturnedOnceInStableOrder(): void
    {
        self::assertSame([1, 2, 4], array_column($this->categories->findWithArticles(), 'id'));
    }

    public function testEmptyBlogReturnsEmptyResults(): void
    {
        $this->connection->exec('DELETE FROM article_category');
        $this->connection->exec('DELETE FROM articles');

        self::assertSame([], $this->categories->findWithArticles());
        self::assertNull($this->articles->findById(1));
        self::assertSame(0, $this->articles->countByCategory(1));
        self::assertSame([], $this->articles->findByCategory(1, ArticleSort::Newest, 3, 0));
        self::assertSame([1 => [], 2 => []], $this->articles->findLatestByCategories([1, 2], 3));
        self::assertSame([], $this->articles->findRelated(1, 3));
        self::assertFalse($this->articles->incrementViews(1));
    }

    public function testArticleLookupHydratesAllFieldsAndCategoriesInUtc(): void
    {
        $timezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Moscow');

        try {
            $article = $this->articles->findById(1);
        } finally {
            date_default_timezone_set($timezone);
        }

        self::assertInstanceOf(Article::class, $article);
        self::assertSame(1, $article->id);
        self::assertSame('articles/1.webp', $article->imagePath);
        self::assertSame('Статья 1 🐘', $article->title);
        self::assertSame('Описание 1', $article->description);
        self::assertSame("Текст 1\n<script>example</script>", $article->text);
        self::assertSame('2026-10-01T09:00:00+00:00', $article->publishedAt->format('c'));
        self::assertSame(5, $article->views);
        self::assertSame([1, 2], array_column($article->categories, 'id'));
        self::assertSame('PHP 🐘', $article->categories[0]->name);
        self::assertSame('<b>Описание</b>', $article->categories[0]->description);
        self::assertNull($this->articles->findById(999));
    }

    public function testCategoryCountIncludesEachArticleOnce(): void
    {
        self::assertSame(7, $this->articles->countByCategory(1));
        self::assertSame(5, $this->articles->countByCategory(2));
        self::assertSame(0, $this->articles->countByCategory(3));
        self::assertSame(0, $this->articles->countByCategory(999));
    }

    public function testNewestPaginationIsStableAndKeepsAllArticleCategories(): void
    {
        $first = $this->articles->findByCategory(1, ArticleSort::Newest, 3, 0);
        $second = $this->articles->findByCategory(1, ArticleSort::Newest, 3, 3);
        $last = $this->articles->findByCategory(1, ArticleSort::Newest, 3, 6);

        self::assertSame([3, 7, 6], array_column($first, 'id'));
        self::assertSame([4, 2, 1], array_column($second, 'id'));
        self::assertSame([9], array_column($last, 'id'));
        self::assertSame([1, 2], array_column($second[0]->categories, 'id'));
        self::assertSame([], $this->articles->findByCategory(1, ArticleSort::Newest, 3, 9));
        self::assertSame([], $this->articles->findByCategory(3, ArticleSort::Newest, 3, 0));
        self::assertSame([], $this->articles->findByCategory(999, ArticleSort::Newest, 3, 0));
    }

    public function testViewsSortUsesDateAndIdToResolveTies(): void
    {
        self::assertSame([3, 7, 6, 2, 4, 1, 9], array_column(
            $this->articles->findByCategory(1, ArticleSort::MostViewed, 10, 0),
            'id',
        ));
        self::assertSame([6, 2], array_column(
            $this->articles->findByCategory(1, ArticleSort::MostViewed, 2, 2),
            'id',
        ));
    }

    public function testLatestArticlesAreLimitedPerCategoryAndIncludeEmptyGroups(): void
    {
        $groups = $this->articles->findLatestByCategories([2, 1, 3, 4, 999, 1], 3);

        self::assertCount(5, $groups);
        self::assertSame([3, 7, 6], array_column($groups[1], 'id'));
        self::assertSame([5, 4, 2], array_column($groups[2], 'id'));
        self::assertSame([], $groups[3]);
        self::assertSame([8], array_column($groups[4], 'id'));
        self::assertSame([], $groups[999]);
        self::assertSame([1, 2], array_column($groups[2][1]->categories, 'id'));
        self::assertSame([], $this->articles->findLatestByCategories([], 3));
    }

    public function testAnArticleCanAppearInMultipleHomePageGroups(): void
    {
        $groups = $this->articles->findLatestByCategories([1, 2], 10);

        self::assertSame([3, 7, 6, 4, 2, 1, 9], array_column($groups[1], 'id'));
        self::assertSame([5, 4, 2, 1, 9], array_column($groups[2], 'id'));
    }

    public function testRelatedArticlesPreferSharedCategoriesThenDateAndId(): void
    {
        self::assertSame([4, 2, 9], array_column($this->articles->findRelated(1, 3), 'id'));
        $related = $this->articles->findRelated(1, 10);

        self::assertSame([4, 2, 9, 3, 7, 6, 5], array_column($related, 'id'));
        self::assertSame([1, 2], array_column($related[0]->categories, 'id'));
        self::assertSame([], $this->articles->findRelated(8, 3));
        self::assertSame([], $this->articles->findRelated(999, 3));
    }

    public function testViewsIncrementPreservesExistingCountAndOtherArticles(): void
    {
        self::assertTrue($this->articles->incrementViews(1));
        self::assertTrue($this->articles->incrementViews(1));
        self::assertSame(7, $this->articles->findById(1)?->views);
        self::assertSame(10, $this->articles->findById(2)?->views);
        self::assertFalse($this->articles->incrementViews(999));
    }

    public function testArticleListsLoadCategoriesInBulk(): void
    {
        $before = $this->selectCount();
        $this->articles->findByCategory(1, ArticleSort::Newest, 10, 0);
        self::assertSame(2, $this->selectCount() - $before);

        $before = $this->selectCount();
        $this->articles->findLatestByCategories([1, 2, 3, 4], 3);
        self::assertSame(2, $this->selectCount() - $before);

        $before = $this->selectCount();
        $this->articles->findRelated(1, 10);
        self::assertSame(2, $this->selectCount() - $before);
    }

    #[DataProvider('invalidPagination')]
    public function testInvalidPaginationIsRejected(int $limit, int $offset): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->articles->findByCategory(1, ArticleSort::Newest, $limit, $offset);
    }

    #[DataProvider('invalidLimits')]
    public function testInvalidLatestLimitIsRejectedEvenWithoutCategories(int $limit): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->articles->findLatestByCategories([], $limit);
    }

    #[DataProvider('invalidLimits')]
    public function testInvalidRelatedLimitIsRejected(int $limit): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->articles->findRelated(1, $limit);
    }

    /** @return iterable<string, array{int, int}> */
    public static function invalidPagination(): iterable
    {
        yield 'zero limit' => [0, 0];
        yield 'negative limit' => [-1, 0];
        yield 'negative offset' => [3, -1];
    }

    /** @return iterable<string, array{int}> */
    public static function invalidLimits(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    private function selectCount(): int
    {
        $statement = $this->connection->query("SHOW SESSION STATUS LIKE 'Com_select'");
        self::assertInstanceOf(PDOStatement::class, $statement);

        return (int) $statement->fetchColumn(1);
    }

    private function seed(): void
    {
        $this->connection->exec("INSERT INTO categories (id, name, description) VALUES
            (1, 'PHP 🐘', '<b>Описание</b>'), (2, 'MySQL', ''), (3, 'Empty', ''), (4, 'CSS', '')");
        $statement = $this->connection->prepare('INSERT INTO articles
            (id, image_path, title, description, body, published_at, views)
            VALUES (?, ?, ?, ?, ?, ?, ?)');
        self::assertInstanceOf(PDOStatement::class, $statement);

        foreach ([
            [1, '2026-10-01 09:00:00', 5],
            [2, '2026-10-02 09:00:00', 10],
            [3, '2026-10-06 09:00:00', 100],
            [4, '2026-10-02 09:00:00', 7],
            [5, '2026-10-04 09:00:00', 100],
            [6, '2026-10-05 09:00:00', 100],
            [7, '2026-10-05 09:00:00', 100],
            [8, '2026-10-07 09:00:00', 999],
            [9, '2026-09-30 09:00:00', 1],
        ] as [$id, $publishedAt, $views]) {
            $statement->execute([
                $id, 'articles/' . $id . '.webp', 'Статья ' . $id . ' 🐘', 'Описание ' . $id,
                'Текст ' . $id . "\n<script>example</script>", $publishedAt, $views,
            ]);
        }

        $this->connection->exec('INSERT INTO article_category (article_id, category_id) VALUES
            (1, 1), (1, 2), (2, 1), (2, 2), (3, 1), (4, 1), (4, 2),
            (5, 2), (6, 1), (7, 1), (8, 4), (9, 1), (9, 2)');
    }
}
