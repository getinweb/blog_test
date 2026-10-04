<?php

declare(strict_types=1);

namespace App\Config;

use SensitiveParameter;

final readonly class DatabaseConfig
{
    public function __construct(
        public string $host,
        public int $port,
        public string $database,
        public string $username,
        #[SensitiveParameter]
        public string $password,
    ) {
    }
}
