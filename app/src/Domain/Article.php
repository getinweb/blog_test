<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class Article
{
    /** @param list<Category> $categories */
    public function __construct(
        public int $id,
        public string $imagePath,
        public string $title,
        public string $description,
        public string $text,
        public DateTimeImmutable $publishedAt,
        public int $views,
        public array $categories,
    ) {
        if ($id < 1 || $views < 0) {
            throw new InvalidArgumentException('Article id must be positive and views must not be negative.');
        }

        foreach (['title' => $title, 'image path' => $imagePath] as $name => $value) {
            if (trim($value) === '' || mb_strlen($value, 'UTF-8') > 255) {
                throw new InvalidArgumentException('Article ' . $name . ' must contain 1 to 255 characters.');
            }
        }

        if (trim($text) === '') {
            throw new InvalidArgumentException('Article text must not be empty.');
        }

        if ($categories === []) {
            throw new InvalidArgumentException('Article must belong to at least one category.');
        }

        $ids = array_map(static fn (Category $category): int => $category->id, $categories);

        if (count(array_unique($ids)) !== count($ids)) {
            throw new InvalidArgumentException('Article categories must not contain duplicate ids.');
        }
    }
}
