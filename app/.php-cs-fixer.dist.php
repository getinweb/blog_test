<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/config',
        __DIR__ . '/public',
        __DIR__ . '/bin',
        __DIR__ . '/db',
        __DIR__ . '/tests',
    ])
    ->append([__FILE__]);

return (new Config())
    ->setRules([
        '@PSR12' => true,
        'declare_strict_types' => true,
    ])
    ->setRiskyAllowed(true)
    ->setCacheFile(__DIR__ . '/var/cache/php-cs-fixer.cache')
    ->setFinder($finder);
