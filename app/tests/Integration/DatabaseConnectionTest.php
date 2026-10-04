<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class DatabaseConnectionTest extends TestCase
{
    public function testConnectionUsesTheTestDatabaseAndUser(): void
    {
        $connection = $this->connect();

        $statement = $connection->query(
            'SELECT DATABASE() AS db, CURRENT_USER() AS user, @@character_set_connection AS charset, @@session.time_zone AS timezone',
        );

        self::assertInstanceOf(PDOStatement::class, $statement);
        self::assertSame([
            'db' => 'blog_test',
            'user' => 'blog_test@%',
            'charset' => 'utf8mb4',
            'timezone' => '+00:00',
        ], $statement->fetch(PDO::FETCH_ASSOC));
        self::assertFalse($connection->getAttribute(PDO::ATTR_EMULATE_PREPARES));
        self::assertSame(PDO::FETCH_ASSOC, $connection->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE));
    }

    public function testDatabaseUserCannotReadSystemAccounts(): void
    {
        $connection = $this->connect();

        $this->expectException(PDOException::class);
        $this->expectExceptionCode('42000');
        $this->expectExceptionMessageIsOrContains('SELECT command denied');

        $connection->query('SELECT User FROM mysql.user');
    }

    private function connect(): PDO
    {
        $factory = require dirname(__DIR__, 2) . '/config/container.php';

        return $factory(getenv())->get(PDO::class);
    }

    public function testMultipleStatementsAreDisabled(): void
    {
        $connection = $this->connect();
        $this->expectException(PDOException::class);

        $connection->exec('SELECT 1; SELECT 2');
    }
}
