<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Article\SessionArticleViewCounter;
use App\Repository\ArticleRepositoryInterface;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SessionArticleViewCounterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/blog-sessions-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
        session_save_path($this->directory);
        ini_set('session.gc_probability', '0');
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_abort();
        }

        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->directory);
    }

    public function testViewsAreStoredPerArticleAndPersistAfterTheSessionIsClosed(): void
    {
        $articles = $this->createMock(ArticleRepositoryInterface::class);
        $articles->expects($this->exactly(2))->method('incrementViews')->willReturn(true);
        $counter = new SessionArticleViewCounter($articles);
        self::assertSame(PHP_SESSION_NONE, session_status());
        self::assertTrue($counter->record(7));
        self::assertSame(PHP_SESSION_NONE, session_status());
        self::assertNotEmpty(session_id());
        $_SESSION = [];

        $nextRequest = new SessionArticleViewCounter($articles);
        self::assertFalse($nextRequest->record(7));
        self::assertTrue($nextRequest->record(8));
        self::assertFalse($nextRequest->record(8));
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testAnotherSessionCanCountTheSameArticleAgain(): void
    {
        $articles = $this->createMock(ArticleRepositoryInterface::class);
        $articles->expects($this->exactly(2))->method('incrementViews')->with(7)->willReturn(true);
        $counter = new SessionArticleViewCounter($articles);
        self::assertTrue($counter->record(7));
        $previousId = session_id();
        session_id('');
        $_SESSION = [];

        self::assertTrue($counter->record(7));
        self::assertNotSame($previousId, session_id());
        self::assertFalse($counter->record(7));
    }

    public function testUnsuccessfulIncrementDoesNotMarkArticleAsViewed(): void
    {
        $articles = $this->createMock(ArticleRepositoryInterface::class);
        $articles->expects($this->exactly(2))->method('incrementViews')->with(7)->willReturn(false, true);
        $counter = new SessionArticleViewCounter($articles);

        self::assertFalse($counter->record(7));
        self::assertTrue($counter->record(7));
        self::assertFalse($counter->record(7));
    }

    public function testDatabaseFailureReleasesSessionAndAllowsRetry(): void
    {
        $attempts = 0;
        $articles = $this->createMock(ArticleRepositoryInterface::class);
        $articles->expects($this->exactly(2))->method('incrementViews')->with(7)->willReturnCallback(
            static function () use (&$attempts): bool {
                if (++$attempts === 1) {
                    throw new RuntimeException('Database unavailable');
                }

                return true;
            },
        );
        $counter = new SessionArticleViewCounter($articles);

        try {
            $counter->record(7);
            self::fail('Database failure must be propagated.');
        } catch (RuntimeException $exception) {
            self::assertSame('Database unavailable', $exception->getMessage());
            self::assertSame(PHP_SESSION_NONE, session_status());
        }

        self::assertTrue($counter->record(7));
        self::assertFalse($counter->record(7));
    }
}
