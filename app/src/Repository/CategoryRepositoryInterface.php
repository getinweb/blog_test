<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Category;

interface CategoryRepositoryInterface
{
    public function findById(int $id): ?Category;

    /** @return list<Category> Ordered by id; empty categories are excluded. */
    public function findWithArticles(): array;
}
