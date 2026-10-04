<?php

declare(strict_types=1);

use App\Config\AppConfig;
use App\Config\ConfigLoader;
use App\Config\DatabaseConfig;
use App\Container\Container;
use App\Container\ContainerInterface;

/** @param array<string, string> $environment */
return static function (array $environment): ContainerInterface {
    $config = (new ConfigLoader(__DIR__))->load($environment);

    return new Container([
        AppConfig::class => static fn (): AppConfig => $config,
        DatabaseConfig::class => static fn (ContainerInterface $container): DatabaseConfig =>
            $container->get(AppConfig::class)->database,
    ]);
};
