<?php

declare(strict_types=1);

namespace App\Database;

use App\Config\DatabaseConfig;
use InvalidArgumentException;
use PDO;
use Pdo\Mysql;

final class PdoConnectionFactory implements ConnectionFactoryInterface
{
    public function create(DatabaseConfig $config): PDO
    {
        foreach ([$config->host, $config->database] as $value) {
            if (str_contains($value, ';') || str_contains($value, "\0")) {
                throw new InvalidArgumentException('DB_HOST and DB_DATABASE must not contain DSN separators.');
            }
        }

        $connection = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config->host, $config->port, $config->database),
            $config->username,
            $config->password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                Mysql::ATTR_MULTI_STATEMENTS => false,
            ],
        );
        $connection->exec("SET time_zone = '+00:00'");

        return $connection;
    }
}
