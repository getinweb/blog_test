<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Category;
use PDO;
use RuntimeException;

final readonly class PdoCategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(private PDO $connection)
    {
    }

    public function findById(int $id): ?Category
    {
        return $this->select('SELECT id, name, description FROM categories WHERE id = ?', [$id])[0] ?? null;
    }

    public function findWithArticles(): array
    {
        return $this->select('SELECT c.id, c.name, c.description FROM categories c
            WHERE EXISTS (SELECT 1 FROM article_category ac WHERE ac.category_id = c.id)
            ORDER BY c.id');
    }

    /**
     * @param list<int> $parameters
     * @return list<Category>
     */
    private function select(string $sql, array $parameters = []): array
    {
        $statement = $this->connection->prepare($sql);

        if ($statement === false) {
            throw new RuntimeException('Cannot prepare category query.');
        }

        $statement->execute($parameters);
        /** @var list<array{id: int, name: string, description: string}> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): Category => new Category(
            $row['id'],
            $row['name'],
            $row['description'],
        ), $rows);
    }
}
