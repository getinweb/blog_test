<?php

declare(strict_types=1);

use App\Config\AppConfig;
use App\Config\ConfigurationException;

try {
    $container = require dirname(__DIR__) . '/config/bootstrap.php';
    $config = $container->get(AppConfig::class);

    printf("Application configuration is valid (%s).\n", $config->environment);
} catch (ConfigurationException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
