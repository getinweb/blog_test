<?php

declare(strict_types=1);

namespace App\Article;

interface ArticleViewCounterInterface
{
    /** Returns true only when this article's view count was increased for the current session. */
    public function record(int $articleId): bool;
}
