<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Database\MigrationException;
use App\Database\MigrationRunner;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MigrationRunnerTest extends TestCase
{
    private PDO $connection;
    private string $directory;
    private bool $cleanupEnabled = false;

    protected function setUp(): void
    {
        $this->connection = $this->connect();
        // Destructive cleanup is limited to our fixtures on the isolated MySQL server.
        self::assertSame('blog_test', $this->scalar('SELECT DATABASE()'));
        $this->cleanupEnabled = true;
        $this->dropTables();
        $this->directory = sys_get_temp_dir() . '/blog-migrations-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        if ($this->cleanupEnabled) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            $this->dropTables();
        }

        if (isset($this->directory) && is_dir($this->directory)) {
            foreach (glob($this->directory . '/*.sql') ?: [] as $file) {
                unlink($file);
            }

            rmdir($this->directory);
        }
    }

    public function testStatusDoesNotCreateTables(): void
    {
        $status = $this->applicationRunner()->status();

        self::assertCount(3, $status);
        self::assertSame([false, false, false], array_column($status, 'applied'));
        self::assertSame(0, $this->tableCount('schema_migrations'));
    }

    public function testMigrationsRunInOrderAndDoNotRepeatOrDeleteData(): void
    {
        $runner = $this->applicationRunner();
        self::assertSame([
            '001_create_categories.sql',
            '002_create_articles.sql',
            '003_create_article_category.sql',
        ], $runner->migrate());

        $this->connection->exec("INSERT INTO categories (name, description) VALUES ('PHP 🐘', 'Описание')");

        self::assertSame([], $runner->migrate());
        self::assertSame([true, true, true], array_column($runner->status(), 'applied'));
        self::assertSame('PHP 🐘', $this->scalar('SELECT name FROM categories'));
    }

    #[DataProvider('invalidChanges')]
    public function testDatabaseConstraintsRejectInvalidChanges(string $sql, string $code): void
    {
        $this->seed();
        $this->expectException(PDOException::class);
        $this->expectExceptionCode($code);

        $this->connection->exec($sql);
    }

    public function testDeletingArticleRemovesItsLinksButKeepsCategories(): void
    {
        $this->seed();

        self::assertSame(0, $this->scalar('SELECT views FROM articles WHERE id = 1'));
        self::assertSame(2, $this->scalar('SELECT COUNT(*) FROM article_category'));
        $this->connection->exec('DELETE FROM articles WHERE id = 1');

        self::assertSame(0, $this->scalar('SELECT COUNT(*) FROM article_category'));
        self::assertSame(2, $this->scalar('SELECT COUNT(*) FROM categories'));
    }

    public function testChangedAppliedMigrationStopsBeforeNewMigrations(): void
    {
        $this->write('001_probe.sql', 'CREATE TABLE migration_probe (id INT PRIMARY KEY)');
        $runner = new MigrationRunner($this->connection, $this->directory);
        $runner->migrate();
        $this->write('001_probe.sql', 'CREATE TABLE migration_probe (id BIGINT PRIMARY KEY)');
        $this->write('002_later.sql', 'CREATE TABLE migration_later_probe (id INT PRIMARY KEY)');

        try {
            $runner->migrate();
            self::fail('Changed applied SQL must not be accepted.');
        } catch (MigrationException $exception) {
            self::assertStringContainsString('changed', $exception->getMessage());
        }

        self::assertSame(0, $this->tableCount('migration_later_probe'));
    }

    public function testMissingAppliedFileIsRejected(): void
    {
        $this->write('001_probe.sql', 'CREATE TABLE migration_probe (id INT PRIMARY KEY)');
        $runner = new MigrationRunner($this->connection, $this->directory);
        $runner->migrate();
        unlink($this->directory . '/001_probe.sql');
        $this->expectException(MigrationException::class);
        $this->expectExceptionMessageIsOrContains('missing');

        $runner->status();
    }

    public function testNewMigrationCannotBeInsertedBeforeAnAppliedOne(): void
    {
        $this->write('002_probe.sql', 'CREATE TABLE migration_probe (id INT PRIMARY KEY)');
        $runner = new MigrationRunner($this->connection, $this->directory);
        $runner->migrate();
        $this->write('001_earlier.sql', 'CREATE TABLE migration_later_probe (id INT PRIMARY KEY)');
        $this->expectException(MigrationException::class);
        $this->expectExceptionMessageIsOrContains('order');

        $runner->migrate();
    }

    public function testFailedMigrationIsNotRecordedAndReleasesTheLock(): void
    {
        $this->write('001_probe.sql', 'CREATE TABLE migration_probe (id INT PRIMARY KEY)');
        $this->write('002_change.sql', 'THIS IS NOT VALID SQL');
        $this->write('003_later.sql', 'CREATE TABLE migration_later_probe (id INT PRIMARY KEY)');
        $runner = new MigrationRunner($this->connection, $this->directory);

        try {
            $runner->migrate();
            self::fail('The invalid SQL must fail.');
        } catch (MigrationException $exception) {
            self::assertStringContainsString('002_change.sql', $exception->getMessage());
        }

        self::assertSame(1, $this->scalar('SELECT COUNT(*) FROM schema_migrations'));
        self::assertSame(1, $this->tableCount('migration_probe'));
        self::assertSame(0, $this->tableCount('migration_later_probe'));
        $this->write('002_change.sql', 'ALTER TABLE migration_probe ADD title VARCHAR(20)');

        // Another connection proves that a failed run did not retain its lock.
        $retry = new MigrationRunner($this->connect(), $this->directory);
        self::assertSame(['002_change.sql', '003_later.sql'], $retry->migrate());
    }

    public function testConcurrentMigrationRunIsRejected(): void
    {
        $other = $this->connect();
        $statement = $other->query("SELECT GET_LOCK(SHA2(CONCAT('blog:migrations:', DATABASE()), 256), 0)");
        self::assertInstanceOf(PDOStatement::class, $statement);
        self::assertSame(1, $statement->fetchColumn());
        $this->expectException(MigrationException::class);
        $this->expectExceptionMessageIsOrContains('Another migration');

        try {
            $this->applicationRunner()->migrate();
        } finally {
            $other->query("SELECT RELEASE_LOCK(SHA2(CONCAT('blog:migrations:', DATABASE()), 256))");
        }
    }

    public function testMigrationCannotCommitAnExistingTransaction(): void
    {
        $this->connection->beginTransaction();
        $this->expectException(MigrationException::class);
        $this->expectExceptionMessageIsOrContains('transaction');

        $this->applicationRunner()->migrate();
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidChanges(): iterable
    {
        yield 'category in use' => ['DELETE FROM categories WHERE id = 1', '23000'];
        yield 'duplicate association' => ['INSERT INTO article_category VALUES (1, 1)', '23000'];
        yield 'missing category' => ['INSERT INTO article_category VALUES (1, 999)', '23000'];
        yield 'missing article' => ['INSERT INTO article_category VALUES (999, 1)', '23000'];
        yield 'negative views' => ['UPDATE articles SET views = -1 WHERE id = 1', 'HY000'];
    }

    private function seed(): void
    {
        $this->applicationRunner()->migrate();
        $this->connection->exec("INSERT INTO categories (id, name, description) VALUES (1, 'PHP', ''), (2, 'MySQL', '')");
        $this->connection->exec("INSERT INTO articles (id, image_path, title, description, body, published_at)
            VALUES (1, 'test.webp', 'Тест', '', 'Текст', '2026-10-04 12:00:00')");
        $this->connection->exec('INSERT INTO article_category (article_id, category_id) VALUES (1, 1), (1, 2)');
    }

    private function applicationRunner(): MigrationRunner
    {
        return new MigrationRunner($this->connection, dirname(__DIR__, 2) . '/db/migrations');
    }

    private function connect(): PDO
    {
        $factory = require dirname(__DIR__, 2) . '/config/container.php';

        return $factory(getenv())->get(PDO::class);
    }

    private function dropTables(): void
    {
        foreach (['article_category', 'articles', 'categories', 'schema_migrations', 'migration_later_probe', 'migration_probe'] as $table) {
            $this->connection->exec('DROP TABLE IF EXISTS ' . $table);
        }
    }

    private function write(string $name, string $sql): void
    {
        file_put_contents($this->directory . '/' . $name, $sql);
    }

    private function scalar(string $sql): mixed
    {
        $statement = $this->connection->query($sql);
        self::assertInstanceOf(PDOStatement::class, $statement);

        return $statement->fetchColumn();
    }

    private function tableCount(string $name): int
    {
        return (int) $this->scalar("SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = " . $this->connection->quote($name));
    }
}
