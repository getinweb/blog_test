<?php

declare(strict_types=1);

namespace App\Database;

interface MigrationRunnerInterface
{
    /** @return list<array{version: string, applied: bool}> */
    public function status(): array;

    /** @return list<string> */
    public function migrate(): array;
}
