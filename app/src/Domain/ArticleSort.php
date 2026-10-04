<?php

declare(strict_types=1);

namespace App\Domain;

enum ArticleSort: string
{
    case Newest = 'date';
    case MostViewed = 'views';
}
