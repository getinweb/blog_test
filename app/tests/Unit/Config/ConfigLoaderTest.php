<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\ConfigLoader;
use App\Config\ConfigurationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigLoaderTest extends TestCase
{
    public function testProductionDefaultsAreUsedWhenEnvironmentIsNotSpecified(): void
    {
        $config = $this->loader()->load(['DB_PASSWORD' => 'secret']);

        self::assertSame('prod', $config->environment);
        self::assertFalse($config->debug);
        self::assertSame('UTC', $config->timezone);
        self::assertSame(12, $config->articlesPerPage);
        self::assertSame(5_242_880, $config->imageMaxBytes);
        self::assertSame('mysql', $config->database->host);
        self::assertSame(3306, $config->database->port);
        self::assertSame('blog', $config->database->database);
        self::assertSame('blog', $config->database->username);
        self::assertSame('secret', $config->database->password);
    }

    public function testDevelopmentSettingsOverrideCommonDefaults(): void
    {
        $config = $this->loader()->load(['APP_ENV' => 'dev', 'DB_PASSWORD' => 'secret']);

        self::assertSame('dev', $config->environment);
        self::assertTrue($config->debug);
    }

    public function testTestDefaultsUseTheIsolatedDatabase(): void
    {
        $config = $this->loader()->load(['APP_ENV' => 'test', 'DB_PASSWORD' => 'secret']);

        self::assertSame('test', $config->environment);
        self::assertFalse($config->debug);
        self::assertSame('mysql-test', $config->database->host);
        self::assertSame('blog_test', $config->database->database);
        self::assertSame('blog_test', $config->database->username);
    }

    public function testExplicitEnvironmentValuesOverrideFilesAndKeepPasswordUnchanged(): void
    {
        $config = $this->loader()->load([
            'APP_ENV' => 'dev',
            'APP_DEBUG' => '0',
            'APP_TIMEZONE' => 'Europe/Moscow',
            'DB_HOST' => 'db.internal',
            'DB_PORT' => '65535',
            'DB_DATABASE' => 'custom_blog',
            'DB_USERNAME' => 'custom_user',
            'DB_PASSWORD' => ' password with spaces ',
            'ARTICLES_PER_PAGE' => '24',
            'IMAGE_MAX_BYTES' => (string) PHP_INT_MAX,
        ]);

        self::assertFalse($config->debug);
        self::assertSame('Europe/Moscow', $config->timezone);
        self::assertSame('db.internal', $config->database->host);
        self::assertSame(65535, $config->database->port);
        self::assertSame('custom_blog', $config->database->database);
        self::assertSame('custom_user', $config->database->username);
        self::assertSame(' password with spaces ', $config->database->password);
        self::assertSame(24, $config->articlesPerPage);
        self::assertSame(PHP_INT_MAX, $config->imageMaxBytes);
    }

    public function testMissingDatabasePasswordIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('DB_PASSWORD');

        $this->loader()->load([]);
    }

    /** @param array<string, string> $values */
    #[DataProvider('invalidValues')]
    public function testInvalidConfigurationIsRejected(array $values, string $name): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($name);

        $this->loader()->load(array_replace(['DB_PASSWORD' => 'secret'], $values));
    }

    /** @return iterable<string, array{array<string, string>, string}> */
    public static function invalidValues(): iterable
    {
        yield 'unknown environment' => [['APP_ENV' => 'staging'], 'APP_ENV'];
        yield 'empty environment' => [['APP_ENV' => ''], 'APP_ENV'];
        yield 'environment path traversal' => [['APP_ENV' => '../common'], 'APP_ENV'];
        yield 'invalid debug' => [['APP_DEBUG' => 'yes'], 'APP_DEBUG'];
        yield 'empty debug' => [['APP_DEBUG' => ''], 'APP_DEBUG'];
        yield 'invalid timezone' => [['APP_TIMEZONE' => 'Invalid/Timezone'], 'APP_TIMEZONE'];
        yield 'empty timezone' => [['APP_TIMEZONE' => ''], 'APP_TIMEZONE'];
        yield 'empty host' => [['DB_HOST' => ''], 'DB_HOST'];
        yield 'empty database' => [['DB_DATABASE' => ''], 'DB_DATABASE'];
        yield 'empty username' => [['DB_USERNAME' => ''], 'DB_USERNAME'];
        yield 'empty password' => [['DB_PASSWORD' => ''], 'DB_PASSWORD'];
        yield 'zero port' => [['DB_PORT' => '0'], 'DB_PORT'];
        yield 'port out of range' => [['DB_PORT' => '65536'], 'DB_PORT'];
        yield 'fractional port' => [['DB_PORT' => '3306.5'], 'DB_PORT'];
        yield 'zero page size' => [['ARTICLES_PER_PAGE' => '0'], 'ARTICLES_PER_PAGE'];
        yield 'negative page size' => [['ARTICLES_PER_PAGE' => '-1'], 'ARTICLES_PER_PAGE'];
        yield 'fractional page size' => [['ARTICLES_PER_PAGE' => '1.5'], 'ARTICLES_PER_PAGE'];
        yield 'non-numeric page size' => [['ARTICLES_PER_PAGE' => '12posts'], 'ARTICLES_PER_PAGE'];
        yield 'empty page size' => [['ARTICLES_PER_PAGE' => ''], 'ARTICLES_PER_PAGE'];
        yield 'zero image limit' => [['IMAGE_MAX_BYTES' => '0'], 'IMAGE_MAX_BYTES'];
        yield 'image limit overflow' => [['IMAGE_MAX_BYTES' => PHP_INT_MAX . '0'], 'IMAGE_MAX_BYTES'];
    }

    private function loader(): ConfigLoader
    {
        return new ConfigLoader(dirname(__DIR__, 3) . '/config');
    }
}
