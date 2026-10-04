<?php

declare(strict_types=1);

namespace App\Database;

interface SeederInterface
{
    /** Returns false without changing data when the blog is not empty. */
    public function seed(): bool;
}
