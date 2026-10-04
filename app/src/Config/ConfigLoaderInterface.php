<?php

declare(strict_types=1);

namespace App\Config;

interface ConfigLoaderInterface
{
    /** @param array<string, string> $environment */
    public function load(array $environment): AppConfig;
}
