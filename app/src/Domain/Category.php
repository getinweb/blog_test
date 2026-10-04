<?php

declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;

final readonly class Category
{
    public function __construct(public int $id, public string $name, public string $description)
    {
        if ($id < 1) {
            throw new InvalidArgumentException('Category id must be positive.');
        }

        if (trim($name) === '' || mb_strlen($name, 'UTF-8') > 255) {
            throw new InvalidArgumentException('Category name must contain 1 to 255 characters.');
        }
    }
}
