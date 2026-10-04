<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Article;
use App\Domain\ArticleSort;

interface ArticleRepositoryInterface
{
    public function findById(int $id): ?Article;

    public function countByCategory(int $categoryId): int;

    /** @return list<Article> Equal sort values are resolved by publication date, then id, descending. */
    public function findByCategory(int $categoryId, ArticleSort $sort, int $limit, int $offset): array;

    /**
     * @param list<int> $categoryIds
     * @return array<int, list<Article>> Requested category id => latest articles (including empty lists).
     */
    public function findLatestByCategories(array $categoryIds, int $limit): array;

    /** @return list<Article> Ranked by shared category count, publication date, then id, descending. */
    public function findRelated(int $articleId, int $limit): array;

    /** Returns false when the article does not exist. */
    public function incrementViews(int $id): bool;
}
