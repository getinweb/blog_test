<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DatabaseConnectionTest extends TestCase
{
    public function testConnectionUsesTheTestDatabaseAndUser(): void
    {
        $connection = $this->connect();

        $statement = $connection->query(
            'SELECT DATABASE() AS db, CURRENT_USER() AS user, @@character_set_database AS charset',
        );

        self::assertInstanceOf(PDOStatement::class, $statement);
        self::assertSame([
            'db' => 'blog_test',
            'user' => 'blog_test@%',
            'charset' => 'utf8mb4',
        ], $statement->fetch(PDO::FETCH_ASSOC));
    }

    public function testDatabaseUserCannotReadSystemAccounts(): void
    {
        $connection = $this->connect();

        $this->expectException(PDOException::class);
        $this->expectExceptionCode('42000');
        $this->expectExceptionMessage('SELECT command denied');

        $connection->query('SELECT User FROM mysql.user');
    }

    private function connect(): PDO
    {
        $password = getenv('DB_PASSWORD');

        if ($password === false) {
            throw new RuntimeException('DB_PASSWORD must be set for integration tests.');
        }

        return new PDO(
            'mysql:host=mysql-test;port=3306;dbname=blog_test;charset=utf8mb4',
            'blog_test',
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }
}
