<?php

declare(strict_types=1);

use App\Config\AppConfig;
use App\Database\SeederInterface;

try {
    $container = require dirname(__DIR__) . '/config/bootstrap.php';

    if ($container->get(AppConfig::class)->environment === 'prod') {
        throw new RuntimeException('Demo seeding is available only in dev and test environments.');
    }

    $seeded = $container->get(SeederInterface::class)->seed();
    echo $seeded ? "Demo blog data seeded.\n" : "Seeding skipped: the blog already contains data.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
