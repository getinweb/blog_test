<?php

declare(strict_types=1);

// Strings match process environment values; ConfigLoader validates and converts them.
// DB_PASSWORD has no default and must be supplied through the environment.
return [
    'APP_DEBUG' => '0',
    'APP_TIMEZONE' => 'UTC',
    'DB_HOST' => 'mysql',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'blog',
    'DB_USERNAME' => 'blog',
    'ARTICLES_PER_PAGE' => '12',
    'IMAGE_MAX_BYTES' => '5242880',
];
