<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Database\MigrationRunnerInterface;
use App\Http\Request;
use App\Http\RequestHandlerInterface;
use Dom\HTMLDocument;
use Dom\XPath;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    private PDO $connection;
    private RequestHandlerInterface $application;

    protected function setUp(): void
    {
        $factory = require dirname(__DIR__, 2) . '/config/container.php';
        $container = $factory(getenv());
        $this->connection = $container->get(PDO::class);
        $statement = $this->connection->query('SELECT DATABASE()');
        self::assertInstanceOf(PDOStatement::class, $statement);
        self::assertSame('blog_test', $statement->fetchColumn());
        $container->get(MigrationRunnerInterface::class)->migrate();
        $this->connection->beginTransaction();
        $this->connection->exec('DELETE FROM article_category');
        $this->connection->exec('DELETE FROM articles');
        $this->connection->exec('DELETE FROM categories');
        $this->application = $container->get(RequestHandlerInterface::class);
    }

    protected function tearDown(): void
    {
        if (isset($this->connection) && $this->connection->inTransaction()) {
            $this->connection->rollBack();
        }
    }

    public function testHomePageUsesTheSharedLayoutAndCompiledStyles(): void
    {
        $response = $this->application->handle(Request::fromServer([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/?page=1',
        ], ['page' => '1']));

        self::assertSame(200, $response->status);
        self::assertSame('text/html; charset=UTF-8', $response->headers['Content-Type']);
        self::assertStringContainsString('<html lang="ru">', $response->body);
        self::assertStringContainsString('href="/assets/css/app.css"', $response->body);
        self::assertStringContainsString('Статей пока нет.', $response->body);
        self::assertStringNotContainsString('<script', $response->body);
    }

    public function testHomePageShowsLatestArticlesPerCategoryWithLinksAndEscaping(): void
    {
        $this->seedHomePage();
        $before = $this->selectCount();
        $response = $this->application->handle(new Request('GET', '/'));
        self::assertSame(3, $this->selectCount() - $before);
        self::assertSame(200, $response->status);
        $xpath = new XPath(HTMLDocument::createFromString($response->body, overrideEncoding: 'UTF-8'));
        $xpath->registerNamespace('h', 'http://www.w3.org/1999/xhtml');

        self::assertSame(1.0, $xpath->evaluate('count(//h:h1)'));
        self::assertSame(2.0, $xpath->evaluate('count(//h:section)'));
        self::assertSame('<b>PHP</b> & язык', $xpath->evaluate('string(//h:h2[@id="category-1"])'));
        self::assertStringNotContainsString('Пустая категория', $response->body);
        self::assertStringNotContainsString('Самая старая статья', $response->body);
        self::assertSame([
            '/article?id=4', '/article?id=3', '/article?id=2',
        ], $this->values($xpath, '//h:section[@aria-labelledby="category-1"]//h:h3/parent::h:a/@href'));
        self::assertSame([
            '/article?id=5', '/article?id=4',
        ], $this->values($xpath, '//h:section[@aria-labelledby="category-2"]//h:h3/parent::h:a/@href'));
        self::assertSame(['/category?id=1', '/category?id=2'], $this->values($xpath, '//h:a[normalize-space(.)="Все статьи"]/@href'));
        self::assertStringContainsString('Описание &lt;img src=x onerror=alert(1)&gt;', $response->body);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $response->body);
        self::assertSame(0.0, $xpath->evaluate('count(//h:script | //*[@onerror])'));
        self::assertSame('2026-09-03T10:00:00+00:00', $xpath->evaluate('string((//h:time)[1]/@datetime)'));
        self::assertSame('03.09.2026', $xpath->evaluate('string((//h:time)[1])'));
        self::assertStringContainsString('Просмотры: 40', $response->body);
        self::assertSame('/images/4.png', $xpath->evaluate('string((//h:article//h:img)[1]/@src)'));
        self::assertSame(5.0, $xpath->evaluate('count(//h:article//h:img[@alt=""])'));
        $statement = $this->connection->query('SELECT views FROM articles ORDER BY id');
        self::assertInstanceOf(PDOStatement::class, $statement);
        self::assertSame([10, 20, 30, 40, 50], $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    #[DataProvider('errorRequests')]
    public function testRoutingErrorsUseHtmlPages(string $method, string $path, int $status, string $message): void
    {
        $response = $this->application->handle(new Request($method, $path));

        self::assertSame($status, $response->status);
        self::assertStringContainsString('<html lang="ru">', $response->body);
        self::assertStringContainsString($message, $response->body);

        if ($status === 405) {
            self::assertSame('GET, HEAD', $response->headers['Allow']);
        }
    }

    #[DataProvider('headRequests')]
    public function testHeadResponsesHaveNoBody(string $path, int $status): void
    {
        $response = $this->application->handle(new Request('HEAD', $path));

        self::assertSame($status, $response->status);
        self::assertSame('', $response->body);
    }

    /** @return iterable<string, array{string, string, int, string}> */
    public static function errorRequests(): iterable
    {
        yield 'missing page' => ['GET', '/missing', 404, 'Страница не найдена'];
        yield 'unsupported method' => ['POST', '/', 405, 'Метод не поддерживается'];
        yield 'missing page with unsupported method' => ['POST', '/missing', 404, 'Страница не найдена'];
    }

    /** @return iterable<string, array{string, int}> */
    public static function headRequests(): iterable
    {
        yield 'home' => ['/', 200];
        yield 'missing page' => ['/missing', 404];
    }

    private function seedHomePage(): void
    {
        $this->connection->exec("INSERT INTO categories (id, name, description) VALUES
            (1, '<b>PHP</b> & язык', 'Описание категории'), (2, 'MySQL', ''), (3, 'Пустая категория', '')");
        $statement = $this->connection->prepare('INSERT INTO articles
            (id, image_path, title, description, body, published_at, views) VALUES (?, ?, ?, ?, ?, ?, ?)');
        self::assertInstanceOf(PDOStatement::class, $statement);

        foreach ([
            [1, '2026-09-01 10:00:00', 'Самая старая статья'],
            [2, '2026-09-02 10:00:00', '<script>alert(1)</script>'],
            [3, '2026-09-03 10:00:00', 'Статья 3'],
            [4, '2026-09-03 10:00:00', 'Статья 4'],
            [5, '2026-09-04 10:00:00', 'Статья 5'],
        ] as [$id, $date, $title]) {
            $statement->execute([
                $id, 'images/' . $id . '.png', $title,
                'Описание <img src=x onerror=alert(1)>', 'Текст статьи', $date, $id * 10,
            ]);
        }

        $this->connection->exec('INSERT INTO article_category (article_id, category_id) VALUES
            (1, 1), (2, 1), (3, 1), (4, 1), (4, 2), (5, 2)');
    }

    /** @return list<string> */
    private function values(XPath $xpath, string $query): array
    {
        $nodes = $xpath->query($query);
        self::assertNotFalse($nodes);

        return array_map(static fn ($node): string => (string) $node->textContent, iterator_to_array($nodes, false));
    }

    private function selectCount(): int
    {
        $statement = $this->connection->query("SHOW SESSION STATUS LIKE 'Com_select'");
        self::assertInstanceOf(PDOStatement::class, $statement);

        return (int) $statement->fetchColumn(1);
    }
}
