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
use PHPUnit\Framework\TestCase;

final class CategoryPageTest extends TestCase
{
    private PDO $connection;
    private RequestHandlerInterface $application;

    protected function setUp(): void
    {
        $factory = require dirname(__DIR__, 2) . '/config/container.php';
        $container = $factory(array_replace(getenv(), ['ARTICLES_PER_PAGE' => '2']));
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
    }

    public function testCategoryRendersEscapedDetailsAndFirstPageWithDefaultSort(): void
    {
        $response = $this->request(['id' => '1']);
        self::assertSame(200, $response->status);
        $xpath = $this->xpath($response);

        self::assertSame('PHP & <b>код</b>', $xpath->evaluate('string(//h:h1)'));
        self::assertStringContainsString('Описание &lt;script&gt;alert(1)&lt;/script&gt;', $response->body);
        self::assertSame(0.0, $xpath->evaluate('count(//h:script)'));
        self::assertSame(['/article?id=5', '/article?id=4'], $this->articleLinks($xpath));
        self::assertSame('date', $xpath->evaluate('string(//h:select[@name="sort"]/h:option[@selected]/@value)'));
        self::assertSame('/category?id=1&sort=date&page=2', $xpath->evaluate('string(//h:a[@rel="next"]/@href)'));
        self::assertSame(0.0, $xpath->evaluate('count(//h:a[@rel="prev"])'));
        self::assertStringContainsString('Страница 1 из 3', $response->body);
        self::assertStringContainsString('Всего: 5', $response->body);
    }

    public function testSortingAndPaginationPreserveCategoryAndSortAndCanBeSubmittedWithoutJavascript(): void
    {
        $response = $this->request(['id' => '1', 'sort' => 'views', 'page' => '2']);
        self::assertSame(200, $response->status);
        $xpath = $this->xpath($response);

        self::assertSame(['/article?id=1', '/article?id=4'], $this->articleLinks($xpath));
        self::assertSame('views', $xpath->evaluate('string(//h:select[@name="sort"]/h:option[@selected]/@value)'));
        self::assertSame('/category?id=1&sort=views&page=1', $xpath->evaluate('string(//h:a[@rel="prev"]/@href)'));
        self::assertSame('/category?id=1&sort=views&page=3', $xpath->evaluate('string(//h:a[@rel="next"]/@href)'));
        self::assertSame('get', $xpath->evaluate('string(//h:form/@method)'));
        self::assertSame('/category', $xpath->evaluate('string(//h:form/@action)'));
        self::assertSame('1', $xpath->evaluate('string(//h:form/h:input[@name="id"]/@value)'));
        self::assertSame(0.0, $xpath->evaluate('count(//h:form//*[@name="page"])'));
        self::assertSame(1.0, $xpath->evaluate('count(//h:button[@type="submit"])'));
        self::assertSame(0.0, $xpath->evaluate('count(//h:script | //*[@onchange])'));
        $reset = $this->xpath($this->request(['id' => '1', 'sort' => 'views']));
        self::assertSame(['/article?id=3', '/article?id=2'], $this->articleLinks($reset));
    }

    public function testLastPageAndSinglePageDoNotLinkToAnExtraPage(): void
    {
        $last = $this->xpath($this->request(['id' => '1', 'page' => '3']));
        self::assertSame(['/article?id=1'], $this->articleLinks($last));
        self::assertSame(0.0, $last->evaluate('count(//h:a[@rel="next"])'));
        self::assertSame('/category?id=1&sort=date&page=2', $last->evaluate('string(//h:a[@rel="prev"]/@href)'));
        $single = $this->xpath($this->request(['id' => '2']));
        self::assertSame(['/article?id=6'], $this->articleLinks($single));
        self::assertSame(0.0, $single->evaluate('count(//h:nav[@aria-label="Страницы статей"])'));
    }

    public function testEmptyCategoryIsAValidPageWithoutPagination(): void
    {
        $response = $this->request(['id' => '3']);
        self::assertSame(200, $response->status);
        self::assertStringContainsString('В этой категории пока нет статей.', $response->body);
        $xpath = $this->xpath($response);
        self::assertSame([], $this->articleLinks($xpath));
        self::assertSame(0.0, $xpath->evaluate('count(//h:nav[@aria-label="Страницы статей"])'));
    }

    /** @param array<string, mixed> $query */
    #[DataProvider('errorQueries')]
    public function testInvalidRequestsUseTheSharedErrorPage(array $query, int $status): void
    {
        $response = $this->request($query);

        self::assertSame($status, $response->status);
        self::assertSame('text/html; charset=UTF-8', $response->headers['Content-Type']);
        self::assertStringContainsString('На главную', $response->body);
        self::assertStringNotContainsString('SQLSTATE', $response->body);
    }

    public function testHeadHasNoBodyAndPostIsNotAllowed(): void
    {
        $head = $this->application->handle(new Request('HEAD', '/category', ['id' => '1']));
        self::assertSame(200, $head->status);
        self::assertSame('', $head->body);
        $invalid = $this->application->handle(new Request('HEAD', '/category', ['id' => '1', 'page' => '0']));
        self::assertSame(400, $invalid->status);
        self::assertSame('', $invalid->body);
        $post = $this->application->handle(new Request('POST', '/category', ['id' => '1']));
        self::assertSame(405, $post->status);
        self::assertSame('GET, HEAD', $post->headers['Allow']);
    }

    /** @return iterable<string, array{array<string, mixed>, int}> */
    public static function errorQueries(): iterable
    {
        yield 'missing category' => [['id' => '999'], 404];
        yield 'missing id' => [[], 400];
        yield 'array page' => [['id' => '1', 'page' => ['1']], 400];
        yield 'unknown sort' => [['id' => '1', 'sort' => 'bad'], 400];
        yield 'page beyond end' => [['id' => '1', 'page' => '4'], 404];
    }

    /** @param array<string, mixed> $query */
    private function request(array $query): Response
    {
        return $this->application->handle(new Request('GET', '/category', $query));
    }

    private function xpath(Response $response): XPath
    {
        $xpath = new XPath(HTMLDocument::createFromString($response->body, overrideEncoding: 'UTF-8'));
        $xpath->registerNamespace('h', 'http://www.w3.org/1999/xhtml');

        return $xpath;
    }

    /** @return list<string> */
    private function articleLinks(XPath $xpath): array
    {
        $nodes = $xpath->query('//h:article/h:a/@href');
        self::assertNotFalse($nodes);

        return array_map(static fn ($node): string => (string) $node->textContent, iterator_to_array($nodes, false));
    }

    private function seed(): void
    {
        $this->connection->exec("INSERT INTO categories (id, name, description) VALUES
            (1, 'PHP & <b>код</b>', 'Описание <script>alert(1)</script>'), (2, 'Другая', ''), (3, 'Пустая', '')");
        $statement = $this->connection->prepare('INSERT INTO articles
            (id, image_path, title, description, body, published_at, views) VALUES (?, ?, ?, ?, ?, ?, ?)');
        self::assertInstanceOf(PDOStatement::class, $statement);

        foreach ([
            [1, '2026-09-01 12:00:00', 90], [2, '2026-09-02 12:00:00', 100],
            [3, '2026-09-03 12:00:00', 100], [4, '2026-09-03 12:00:00', 20],
            [5, '2026-09-04 12:00:00', 0], [6, '2026-09-05 12:00:00', 999],
        ] as [$id, $date, $views]) {
            $statement->execute([$id, 'images/test.png', 'Статья ' . $id, 'Описание', 'Текст', $date, $views]);
        }

        $this->connection->exec('INSERT INTO article_category (article_id, category_id) VALUES
            (1, 1), (2, 1), (3, 1), (4, 1), (5, 1), (6, 2)');
    }
}
