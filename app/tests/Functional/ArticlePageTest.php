<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Database\MigrationRunnerInterface;
use App\Http\Request;
use App\Http\RequestHandlerInterface;
use App\Http\Response;
use Dom\HTMLDocument;
use Dom\XPath;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ArticlePageTest extends TestCase
{
    private PDO $connection;
    private RequestHandlerInterface $application;
    private string $sessionDirectory;

    protected function setUp(): void
    {
        $this->sessionDirectory = sys_get_temp_dir() . '/blog-article-sessions-' . bin2hex(random_bytes(8));
        mkdir($this->sessionDirectory);
        session_save_path($this->sessionDirectory);
        ini_set('session.gc_probability', '0');
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
        $this->seed();
        $this->application = $container->get(RequestHandlerInterface::class);
    }

    protected function tearDown(): void
    {
        if (isset($this->connection) && $this->connection->inTransaction()) {
            $this->connection->rollBack();
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_abort();
        }

        foreach (glob($this->sessionDirectory . '/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->sessionDirectory);
    }

    public function testArticleShowsEscapedDetailsAllCategoriesAndUpdatedViews(): void
    {
        $response = $this->application->handle(new Request('GET', '/article', ['id' => '1']));
        self::assertSame(200, $response->status);
        self::assertSame('private, no-store', $response->headers['Cache-Control']);
        $xpath = $this->xpath($response);

        self::assertSame('PHP & <b>код</b>', $xpath->evaluate('string(//h:h1)'));
        self::assertSame('Описание <script>alert(1)</script>', $xpath->evaluate('string(//h:p[@class="article-detail__description"])'));
        self::assertSame('/images/test.png', $xpath->evaluate('string(//h:article//h:img/@src)'));
        self::assertSame('2026-09-01T12:00:00+00:00', $xpath->evaluate('string(//h:time/@datetime)'));
        self::assertSame('01.09.2026', $xpath->evaluate('string(//h:time)'));
        self::assertSame(2.0, $xpath->evaluate('count(//h:nav[@aria-label="Категории статьи"]//h:a)'));
        self::assertSame('/category?id=1', $xpath->evaluate('string((//h:nav[@aria-label="Категории статьи"]//h:a)[1]/@href)'));
        self::assertSame('/category?id=2', $xpath->evaluate('string((//h:nav[@aria-label="Категории статьи"]//h:a)[2]/@href)'));
        self::assertSame('PHP <b>основы</b>', $xpath->evaluate('string((//h:nav[@aria-label="Категории статьи"]//h:a)[1])'));
        self::assertSame("Первая <script>alert(2)</script> строка.\n\nВторая & последняя строка.", $xpath->evaluate('string(//h:div[@class="article-detail__text"])'));
        self::assertSame(0.0, $xpath->evaluate('count(//h:script | //h:b)'));
        self::assertStringContainsString('Просмотры: 6', $response->body);
        self::assertSame(6, $this->views(1));
    }

    public function testRefreshCountsOncePerArticleAndANewSessionCountsAgain(): void
    {
        $request = new Request('GET', '/article', ['id' => '1']);
        self::assertSame(200, $this->application->handle($request)->status);
        $repeat = $this->application->handle($request);
        self::assertSame(200, $repeat->status);
        self::assertStringContainsString('Просмотры: 6', $repeat->body);
        self::assertSame(6, $this->views(1));

        $other = $this->application->handle(new Request('GET', '/article', ['id' => '2']));
        self::assertSame(200, $other->status);
        self::assertSame(1, $this->views(2));
        self::assertSame(0.0, $this->xpath($other)->evaluate('count(//h:p[@class="article-detail__description"])'));
        session_id('');
        $_SESSION = [];

        self::assertSame(200, $this->application->handle($request)->status);
        self::assertSame(7, $this->views(1));
    }

    public function testHeadPostAndListingPagesDoNotCountViewsOrStartSession(): void
    {
        $head = $this->application->handle(new Request('HEAD', '/article', ['id' => '1']));
        self::assertSame(200, $head->status);
        self::assertSame('', $head->body);
        $post = $this->application->handle(new Request('POST', '/article', ['id' => '1']));
        self::assertSame(405, $post->status);
        self::assertSame('GET, HEAD', $post->headers['Allow']);
        self::assertSame(200, $this->application->handle(new Request('GET', '/'))->status);
        self::assertSame(200, $this->application->handle(new Request('GET', '/category', ['id' => '1']))->status);
        self::assertSame(5, $this->views(1));
        self::assertSame('', session_id());
        self::assertSame([], glob($this->sessionDirectory . '/*'));
    }

    /** @param array<string, mixed> $query */
    #[DataProvider('invalidRequests')]
    public function testInvalidRequestsDoNotCountViewsOrStartSession(array $query, int $status): void
    {
        $response = $this->application->handle(new Request('GET', '/article', $query));
        self::assertSame($status, $response->status);
        self::assertSame('text/html; charset=UTF-8', $response->headers['Content-Type']);
        self::assertStringContainsString('На главную', $response->body);
        self::assertStringNotContainsString('SQLSTATE', $response->body);
        $head = $this->application->handle(new Request('HEAD', '/article', $query));
        self::assertSame($status, $head->status);
        self::assertSame('', $head->body);
        self::assertSame(5, $this->views(1));
        self::assertSame('', session_id());
        self::assertSame([], glob($this->sessionDirectory . '/*'));
    }

    /** @return iterable<string, array{array<string, mixed>, int}> */
    public static function invalidRequests(): iterable
    {
        yield 'missing id' => [[], 400];
        yield 'zero' => [['id' => '0'], 400];
        yield 'array' => [['id' => ['1']], 400];
        yield 'overflow' => [['id' => PHP_INT_MAX . '0'], 400];
        yield 'missing article' => [['id' => '999'], 404];
    }

    private function xpath(Response $response): XPath
    {
        $xpath = new XPath(HTMLDocument::createFromString($response->body, overrideEncoding: 'UTF-8'));
        $xpath->registerNamespace('h', 'http://www.w3.org/1999/xhtml');

        return $xpath;
    }

    private function views(int $articleId): int
    {
        $statement = $this->connection->prepare('SELECT views FROM articles WHERE id = ?');
        self::assertInstanceOf(PDOStatement::class, $statement);
        $statement->execute([$articleId]);

        return (int) $statement->fetchColumn();
    }

    private function seed(): void
    {
        $this->connection->exec("INSERT INTO categories (id, name, description) VALUES
            (1, 'PHP <b>основы</b>', ''), (2, 'MySQL', '')");
        $statement = $this->connection->prepare('INSERT INTO articles
            (id, image_path, title, description, body, published_at, views) VALUES (?, ?, ?, ?, ?, ?, ?)');
        self::assertInstanceOf(PDOStatement::class, $statement);
        $statement->execute([
            1, 'images/test.png', 'PHP & <b>код</b>', 'Описание <script>alert(1)</script>',
            "Первая <script>alert(2)</script> строка.\n\nВторая & последняя строка.", '2026-09-01 12:00:00', 5,
        ]);
        $statement->execute([2, 'images/test.png', 'Без описания', '', 'Текст', '2026-09-02 12:00:00', 0]);
        $this->connection->exec('INSERT INTO article_category (article_id, category_id) VALUES (1, 1), (1, 2), (2, 1)');
    }
}
