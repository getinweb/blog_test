<?php

declare(strict_types=1);

namespace App\Database;

use App\Image\ImageStorageInterface;
use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

final readonly class BlogSeeder implements SeederInterface
{
    public function __construct(
        private PDO $connection,
        private ImageStorageInterface $images,
        private string $fixture,
    ) {
    }

    public function seed(): bool
    {
        if ($this->connection->inTransaction()) {
            throw new RuntimeException('Seeding cannot run inside an existing transaction.');
        }

        $locked = $this->query("SELECT GET_LOCK(SHA2(CONCAT('blog:seed:', DATABASE()), 256), 0)")->fetchColumn();

        if ($locked !== 1) {
            throw new RuntimeException('Another seed process is running or the seed lock is unavailable.');
        }

        try {
            return $this->seedEmptyDatabase();
        } finally {
            $this->query("SELECT RELEASE_LOCK(SHA2(CONCAT('blog:seed:', DATABASE()), 256))");
        }
    }

    private function seedEmptyDatabase(): bool
    {
        $this->connection->beginTransaction();

        try {
            if ($this->query('SELECT (SELECT COUNT(*) FROM categories) + (SELECT COUNT(*) FROM articles)')->fetchColumn() !== 0) {
                $this->connection->rollBack();

                return false;
            }

            /**
             * @var array{
             *     categories: array<string, array{name: string, description: string}>,
             *     articles: list<array{
             *         image: string, title: string, description: string, text: string,
             *         published_at: string, views: int, categories: non-empty-list<string>
             *     }>
             * } $data
             */
            $data = require $this->fixture;
            $categories = [];

            foreach ($data['categories'] as $key => $category) {
                $this->query('INSERT INTO categories (name, description) VALUES (?, ?)', [
                    $category['name'], $category['description'],
                ]);
                $categories[$key] = (int) $this->connection->lastInsertId();
            }

            $images = [];

            foreach ($data['articles'] as $article) {
                $image = $images[$article['image']] ??= $this->images->store(dirname($this->fixture) . '/' . $article['image']);
                $this->query('INSERT INTO articles (image_path, title, description, body, published_at, views)
                    VALUES (?, ?, ?, ?, ?, ?)', [
                    $image, $article['title'], $article['description'], $article['text'],
                    $article['published_at'], $article['views'],
                ]);
                $articleId = (int) $this->connection->lastInsertId();

                foreach ($article['categories'] as $key) {
                    $categoryId = $categories[$key] ?? throw new RuntimeException('Unknown seed category: ' . $key);
                    $this->query('INSERT INTO article_category (article_id, category_id) VALUES (?, ?)', [$articleId, $categoryId]);
                }
            }

            $this->connection->commit();

            return true;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }

    /** @param list<int|string> $parameters */
    private function query(string $sql, array $parameters = []): PDOStatement
    {
        $statement = $this->connection->prepare($sql);

        if ($statement === false) {
            throw new RuntimeException('Cannot prepare seed query.');
        }

        $statement->execute($parameters);

        return $statement;
    }
}
