<?php

declare(strict_types=1);

namespace App\Config;

final readonly class AppConfig
{
    public function __construct(
        public string $environment,
        public bool $debug,
        public string $timezone,
        public DatabaseConfig $database,
        public int $articlesPerPage,
        public int $imageMaxBytes,
    ) {
    }
}
