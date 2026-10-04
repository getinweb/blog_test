<?php

declare(strict_types=1);

use App\Database\MigrationRunnerInterface;

$command = $argv[1] ?? 'status';

if (!in_array($command, ['up', 'status'], true)) {
    fwrite(STDERR, "Usage: php bin/migrate.php [up|status]\n");
    exit(2);
}

try {
    $container = require dirname(__DIR__) . '/config/bootstrap.php';
    $runner = $container->get(MigrationRunnerInterface::class);

    if ($command === 'status') {
        foreach ($runner->status() as $migration) {
            printf("[%s] %s\n", $migration['applied'] ? 'applied' : 'pending', $migration['version']);
        }
    } else {
        $applied = $runner->migrate();

        foreach ($applied as $version) {
            printf("Applied: %s\n", $version);
        }

        if ($applied === []) {
            echo "No pending migrations.\n";
        }
    }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
