<?php

declare(strict_types=1);

namespace App\Article;

use App\Repository\ArticleRepositoryInterface;
use RuntimeException;

final readonly class SessionArticleViewCounter implements ArticleViewCounterInterface
{
    public function __construct(private ArticleRepositoryInterface $articles)
    {
    }

    public function record(int $articleId): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !session_start()) {
            throw new RuntimeException('Cannot start article view session.');
        }

        try {
            // Keep the session locked until the increment and marker are both complete.
            if (isset($_SESSION['viewed_articles'][$articleId]) || !$this->articles->incrementViews($articleId)) {
                return false;
            }

            $_SESSION['viewed_articles'][$articleId] = true;

            return true;
        } finally {
            session_write_close();
        }
    }
}
