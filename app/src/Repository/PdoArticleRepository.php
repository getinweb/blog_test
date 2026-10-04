<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Article;
use App\Domain\ArticleSort;
use App\Domain\Category;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use PDOStatement;
use RuntimeException;

/**
 * @phpstan-type ArticleRow array{
 *     id: int, image_path: string, title: string, description: string, body: string,
 *     published_at: string, views: int
 * }
 */
final readonly class PdoArticleRepository implements ArticleRepositoryInterface
{
    public function __construct(private PDO $connection)
    {
    }

    public function findById(int $id): ?Article
    {
        return $this->findArticles('SELECT * FROM articles WHERE id = ?', [$id])[0] ?? null;
    }

    public function countByCategory(int $categoryId): int
    {
        return (int) $this->query('SELECT COUNT(*) FROM article_category WHERE category_id = ?', [$categoryId])->fetchColumn();
    }

    public function findByCategory(int $categoryId, ArticleSort $sort, int $limit, int $offset): array
    {
        $this->validatePagination($limit, $offset);
        $order = match ($sort) {
            ArticleSort::Newest => 'a.published_at DESC, a.id DESC',
            ArticleSort::MostViewed => 'a.views DESC, a.published_at DESC, a.id DESC',
        };

        return $this->findArticles('SELECT a.* FROM articles a
            INNER JOIN article_category ac ON ac.article_id = a.id
            WHERE ac.category_id = ? ORDER BY ' . $order . ' LIMIT ? OFFSET ?', [$categoryId, $limit, $offset]);
    }

    public function findLatestByCategories(array $categoryIds, int $limit): array
    {
        $this->validatePagination($limit);

        if ($categoryIds === []) {
            return [];
        }

        $categoryIds = array_values(array_unique($categoryIds));
        $groups = array_fill_keys($categoryIds, []);
        $placeholders = implode(', ', array_fill(0, count($categoryIds), '?'));
        // Rank ids first, so the database returns only the requested number of articles per category.
        /**
         * @var list<array{
         *     id: int, image_path: string, title: string, description: string, body: string,
         *     published_at: string, views: int, selected_category_id: int
         * }> $rows
         */
        $rows = $this->query('SELECT a.*, ranked.category_id AS selected_category_id
            FROM (
                SELECT ac.article_id, ac.category_id,
                    ROW_NUMBER() OVER (
                        PARTITION BY ac.category_id ORDER BY a.published_at DESC, a.id DESC
                    ) AS position
                FROM article_category ac
                INNER JOIN articles a ON a.id = ac.article_id
                WHERE ac.category_id IN (' . $placeholders . ')
            ) ranked
            INNER JOIN articles a ON a.id = ranked.article_id
            WHERE ranked.position <= ?
            ORDER BY ranked.category_id, ranked.position', [...$categoryIds, $limit])->fetchAll(PDO::FETCH_ASSOC);
        $articles = $this->hydrate($rows);

        foreach ($rows as $row) {
            $groups[$row['selected_category_id']][] = $articles[$row['id']];
        }

        return $groups;
    }

    public function findRelated(int $articleId, int $limit): array
    {
        $this->validatePagination($limit);

        return $this->findArticles('SELECT a.* FROM articles a
            INNER JOIN (
                SELECT candidate.article_id, COUNT(*) AS shared_categories
                FROM article_category reference
                INNER JOIN article_category candidate ON candidate.category_id = reference.category_id
                    AND candidate.article_id <> reference.article_id
                WHERE reference.article_id = ?
                GROUP BY candidate.article_id
            ) related ON related.article_id = a.id
            ORDER BY related.shared_categories DESC, a.published_at DESC, a.id DESC
            LIMIT ?', [$articleId, $limit]);
    }

    public function incrementViews(int $id): bool
    {
        return $this->query('UPDATE articles SET views = views + 1 WHERE id = ?', [$id])->rowCount() === 1;
    }

    /**
     * @param list<int> $parameters
     * @return list<Article>
     */
    private function findArticles(string $sql, array $parameters): array
    {
        /** @var list<ArticleRow> $rows */
        $rows = $this->query($sql, $parameters)->fetchAll(PDO::FETCH_ASSOC);

        return array_values($this->hydrate($rows));
    }

    /**
     * @param list<ArticleRow> $rows
     * @return array<int, Article>
     */
    private function hydrate(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $ids = array_values(array_unique(array_column($rows, 'id')));
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        /** @var list<array{article_id: int, id: int, name: string, description: string}> $categoryRows */
        $categoryRows = $this->query('SELECT ac.article_id, c.id, c.name, c.description
            FROM article_category ac INNER JOIN categories c ON c.id = ac.category_id
            WHERE ac.article_id IN (' . $placeholders . ')
            ORDER BY ac.article_id, c.id', $ids)->fetchAll(PDO::FETCH_ASSOC);
        $categories = [];

        foreach ($categoryRows as $row) {
            $categories[$row['article_id']][] = new Category($row['id'], $row['name'], $row['description']);
        }

        $articles = [];
        $utc = new DateTimeZone('UTC');

        foreach ($rows as $row) {
            $articles[$row['id']] ??= new Article(
                $row['id'],
                $row['image_path'],
                $row['title'],
                $row['description'],
                $row['body'],
                new DateTimeImmutable($row['published_at'], $utc),
                $row['views'],
                $categories[$row['id']] ?? [],
            );
        }

        return $articles;
    }

    private function validatePagination(int $limit, int $offset = 0): void
    {
        if ($limit < 1 || $offset < 0) {
            throw new InvalidArgumentException('Limit must be positive and offset must not be negative.');
        }
    }

    /** @param list<int> $parameters */
    private function query(string $sql, array $parameters): PDOStatement
    {
        $statement = $this->connection->prepare($sql);

        if ($statement === false) {
            throw new RuntimeException('Cannot prepare article query.');
        }

        foreach ($parameters as $index => $value) {
            $statement->bindValue($index + 1, $value, PDO::PARAM_INT);
        }

        $statement->execute();

        return $statement;
    }
}
