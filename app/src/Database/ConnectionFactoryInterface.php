<?php

declare(strict_types=1);

namespace App\Database;

use App\Config\DatabaseConfig;
use PDO;

interface ConnectionFactoryInterface
{
    public function create(DatabaseConfig $config): PDO;
}
