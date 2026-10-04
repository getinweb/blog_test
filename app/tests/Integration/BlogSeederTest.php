<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Database\BlogSeeder;
use App\Database\MigrationRunnerInterface;
use App\Database\SeederInterface;
use App\Image\ImageException;
use App\Image\ImageStorageInterface;
use App\Image\LocalImageStorage;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BlogSeederTest extends TestCase
{
    private PDO $connection;
    private string $directory;
    private SeederInterface $seeder;
    private bool $cleanupEnabled = false;

    protected function setUp(): void
    {
        $factory = require dirname(__DIR__, 2) . '/config/container.php';
        $container = $factory(getenv());
        $this->connection = $container->get(PDO::class);
        self::assertSame('blog_test', $this->scalar('SELECT DATABASE()'));
        $container->get(MigrationRunnerInterface::class)->migrate();
        $this->cleanupEnabled = true;
        $this->clearData();
        $this->directory = sys_get_temp_dir() . '/blog-seed-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
        $this->seeder = new BlogSeeder(
            $this->connection,
            new LocalImageStorage($this->directory, 5242880),
            dirname(__DIR__, 2) . '/db/seeds/blog.php',
        );
    }

    protected function tearDown(): void
    {
        if ($this->cleanupEnabled) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            $this->clearData();
        }

        if (isset($this->directory) && is_dir($this->directory)) {
            foreach (glob($this->directory . '/*') ?: [] as $file) {
                unlink($file);
            }

            rmdir($this->directory);
        }
    }

    public function testSeedsDemoDataWithImagesAndCasesForAllBlogPages(): void
    {
        self::assertTrue($this->seeder->seed());
        self::assertSame(4, $this->scalar('SELECT COUNT(*) FROM categories'));
        self::assertSame(30, $this->scalar('SELECT COUNT(*) FROM articles'));
        self::assertSame(45, $this->scalar('SELECT COUNT(*) FROM article_category'));
        self::assertSame(20, $this->scalar("SELECT COUNT(*) FROM article_category ac
            INNER JOIN categories c ON c.id = ac.category_id WHERE c.name = 'PHP'"));
        self::assertSame(1, $this->scalar('SELECT COUNT(*) FROM categories c
            WHERE NOT EXISTS (SELECT 1 FROM article_category ac WHERE ac.category_id = c.id)'));
        self::assertSame(0, $this->scalar('SELECT COUNT(*) FROM articles a
            WHERE NOT EXISTS (SELECT 1 FROM article_category ac WHERE ac.article_id = a.id)'));
        self::assertGreaterThan(1, $this->scalar('SELECT COUNT(DISTINCT published_at) FROM articles'));
        self::assertGreaterThan(1, $this->scalar('SELECT COUNT(DISTINCT views) FROM articles'));
        $statement = $this->connection->query('SELECT DISTINCT image_path FROM articles');
        self::assertInstanceOf(PDOStatement::class, $statement);
        $paths = $statement->fetchAll(PDO::FETCH_COLUMN);
        self::assertCount(3, $paths);

        foreach ($paths as $path) {
            self::assertIsString($path);
            self::assertMatchesRegularExpression('~^images/[a-f0-9]{64}\.(jpg|png|webp)$~', $path);
            self::assertFileExists($this->directory . '/' . basename($path));
        }

        self::assertFalse($this->connection->inTransaction());
    }

    public function testRepeatedRunPreservesEditedDataAndDoesNotDuplicateImages(): void
    {
        $this->seeder->seed();
        $this->connection->exec("UPDATE articles SET title = 'Edited', views = 999");
        $files = glob($this->directory . '/*');

        self::assertFalse($this->seeder->seed());
        self::assertSame(30, $this->scalar("SELECT COUNT(*) FROM articles WHERE title = 'Edited' AND views = 999"));
        self::assertSame(4, $this->scalar('SELECT COUNT(*) FROM categories'));
        self::assertSame($files, glob($this->directory . '/*'));
    }

    public function testExistingCategoryPreventsSeeding(): void
    {
        $this->connection->exec("INSERT INTO categories (name, description) VALUES ('Existing', 'Keep me')");

        self::assertFalse($this->seeder->seed());
        self::assertSame(1, $this->scalar('SELECT COUNT(*) FROM categories'));
        self::assertSame('Keep me', $this->scalar('SELECT description FROM categories'));
        self::assertSame(0, $this->scalar('SELECT COUNT(*) FROM articles'));
        self::assertSame([], glob($this->directory . '/*'));
    }

    public function testExistingArticlePreventsSeedingEvenWithoutCategories(): void
    {
        $this->connection->exec("INSERT INTO articles (image_path, title, description, body, published_at)
            VALUES ('existing.png', 'Existing', '', 'Keep me', '2026-09-01 12:00:00')");

        self::assertFalse($this->seeder->seed());
        self::assertSame(0, $this->scalar('SELECT COUNT(*) FROM categories'));
        self::assertSame(1, $this->scalar('SELECT COUNT(*) FROM articles'));
    }

    public function testFailureAfterSomeInsertsRollsBackTheWholeDatasetAndAllowsRetry(): void
    {
        $images = new class () implements ImageStorageInterface {
            public int $calls = 0;

            public function store(string $sourcePath): string
            {
                if (++$this->calls === 2) {
                    throw new ImageException('Fixture image failed.');
                }

                return 'images/fixture.png';
            }
        };
        $seeder = new BlogSeeder($this->connection, $images, dirname(__DIR__, 2) . '/db/seeds/blog.php');

        try {
            $seeder->seed();
            self::fail('The image failure must abort the seed.');
        } catch (ImageException $exception) {
            self::assertSame('Fixture image failed.', $exception->getMessage());
        }

        self::assertSame(2, $images->calls);
        self::assertFalse($this->connection->inTransaction());
        self::assertSame(0, $this->scalar('SELECT COUNT(*) FROM categories'));
        self::assertSame(0, $this->scalar('SELECT COUNT(*) FROM articles'));
        self::assertSame(0, $this->scalar('SELECT COUNT(*) FROM article_category'));
        // A different connection verifies that the failed seeder released its named lock.
        $factory = require dirname(__DIR__, 2) . '/config/container.php';
        $retry = new BlogSeeder(
            $factory(getenv())->get(PDO::class),
            new LocalImageStorage($this->directory, 5242880),
            dirname(__DIR__, 2) . '/db/seeds/blog.php',
        );
        self::assertTrue($retry->seed());
    }

    public function testConcurrentSeederIsRejected(): void
    {
        $factory = require dirname(__DIR__, 2) . '/config/container.php';
        $other = $factory(getenv())->get(PDO::class);
        $statement = $other->query("SELECT GET_LOCK(SHA2(CONCAT('blog:seed:', DATABASE()), 256), 0)");
        self::assertInstanceOf(PDOStatement::class, $statement);
        self::assertSame(1, $statement->fetchColumn());
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Another seed');

        try {
            $this->seeder->seed();
        } finally {
            $other->query("SELECT RELEASE_LOCK(SHA2(CONCAT('blog:seed:', DATABASE()), 256))");
        }
    }

    public function testSeederDoesNotCommitAnExistingTransaction(): void
    {
        $this->connection->beginTransaction();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('transaction');

        $this->seeder->seed();
    }

    public function testSeederIsRegisteredInTheApplicationContainer(): void
    {
        $factory = require dirname(__DIR__, 2) . '/config/container.php';

        self::assertInstanceOf(BlogSeeder::class, $factory(getenv())->get(SeederInterface::class));
    }

    private function clearData(): void
    {
        $this->connection->exec('DELETE FROM article_category');
        $this->connection->exec('DELETE FROM articles');
        $this->connection->exec('DELETE FROM categories');
    }

    private function scalar(string $sql): mixed
    {
        $statement = $this->connection->query($sql);
        self::assertInstanceOf(PDOStatement::class, $statement);

        return $statement->fetchColumn();
    }
}
