<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

foreach ([
    'APP_ENV' => 'test',
    'DB_HOST' => 'mysql-test',
    'DB_DATABASE' => 'blog_test',
    'DB_USERNAME' => 'blog_test',
] as $name => $expected) {
    if (getenv($name) !== $expected) {
        throw new RuntimeException('Tests require the isolated test environment. Use make test.');
    }
}
