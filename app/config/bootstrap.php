<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

$factory = require __DIR__ . '/container.php';

return $factory(getenv());
