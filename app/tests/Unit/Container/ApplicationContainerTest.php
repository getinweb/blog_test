<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use App\Config\AppConfig;
use App\Config\ConfigurationException;
use App\Config\DatabaseConfig;
use App\Container\ContainerInterface;
use App\Repository\ArticleRepositoryInterface;
use App\Repository\CategoryRepositoryInterface;
use Closure;
use PHPUnit\Framework\TestCase;

final class ApplicationContainerTest extends TestCase
{
    public function testApplicationRegistersSharedConfigurationObjects(): void
    {
        $container = ($this->factory())(['APP_ENV' => 'dev', 'DB_PASSWORD' => 'secret']);
        $config = $container->get(AppConfig::class);

        self::assertSame('dev', $config->environment);
        self::assertSame($config, $container->get(AppConfig::class));
        self::assertSame($config->database, $container->get(DatabaseConfig::class));
    }

    public function testInvalidConfigurationPreventsApplicationStartup(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('DB_PASSWORD');

        ($this->factory())([]);
    }

    public function testRepositoriesAreRegisteredWithoutOpeningADatabaseConnection(): void
    {
        $container = ($this->factory())([
            'APP_ENV' => 'dev', 'DB_HOST' => 'unused.invalid', 'DB_PASSWORD' => 'secret',
        ]);

        self::assertTrue($container->has(CategoryRepositoryInterface::class));
        self::assertTrue($container->has(ArticleRepositoryInterface::class));
    }

    /** @return Closure(array<string, string>): ContainerInterface */
    private function factory(): Closure
    {
        return require dirname(__DIR__, 3) . '/config/container.php';
    }
}
