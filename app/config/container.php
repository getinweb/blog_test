<?php

declare(strict_types=1);

use App\Config\AppConfig;
use App\Config\ConfigLoader;
use App\Config\DatabaseConfig;
use App\Container\Container;
use App\Container\ContainerInterface;
use App\Controller\CategoryController;
use App\Controller\HomeController;
use App\Database\BlogSeeder;
use App\Database\ConnectionFactoryInterface;
use App\Database\MigrationRunner;
use App\Database\MigrationRunnerInterface;
use App\Database\PdoConnectionFactory;
use App\Database\SeederInterface;
use App\Http\HttpKernel;
use App\Http\RequestHandlerInterface;
use App\Http\Router;
use App\Image\ImageStorageInterface;
use App\Image\LocalImageStorage;
use App\Repository\ArticleRepositoryInterface;
use App\Repository\CategoryRepositoryInterface;
use App\Repository\PdoArticleRepository;
use App\Repository\PdoCategoryRepository;
use App\View\SmartyRenderer;
use App\View\TemplateRendererInterface;
use Smarty\Smarty;

/** @param array<string, string> $environment */
return static function (array $environment): ContainerInterface {
    $config = (new ConfigLoader(__DIR__))->load($environment);

    return new Container([
        AppConfig::class => static fn (): AppConfig => $config,
        DatabaseConfig::class => static fn (ContainerInterface $container): DatabaseConfig =>
            $container->get(AppConfig::class)->database,
        ConnectionFactoryInterface::class => static fn (): PdoConnectionFactory => new PdoConnectionFactory(),
        PDO::class => static fn (ContainerInterface $container): PDO =>
            $container->get(ConnectionFactoryInterface::class)->create($container->get(DatabaseConfig::class)),
        MigrationRunnerInterface::class => static fn (ContainerInterface $container): MigrationRunner =>
            new MigrationRunner($container->get(PDO::class), dirname(__DIR__) . '/db/migrations'),
        CategoryRepositoryInterface::class => static fn (ContainerInterface $container): PdoCategoryRepository =>
            new PdoCategoryRepository($container->get(PDO::class)),
        ArticleRepositoryInterface::class => static fn (ContainerInterface $container): PdoArticleRepository =>
            new PdoArticleRepository($container->get(PDO::class)),
        ImageStorageInterface::class => static function (ContainerInterface $container): LocalImageStorage {
            $config = $container->get(AppConfig::class);
            $directory = dirname(__DIR__) . '/var/images' . ($config->environment === 'test' ? '/test' : '');

            return new LocalImageStorage($directory, $config->imageMaxBytes);
        },
        SeederInterface::class => static fn (ContainerInterface $container): BlogSeeder => new BlogSeeder(
            $container->get(PDO::class),
            $container->get(ImageStorageInterface::class),
            dirname(__DIR__) . '/db/seeds/blog.php',
        ),
        Smarty::class => static function (ContainerInterface $container): Smarty {
            $config = $container->get(AppConfig::class);
            $cacheDirectory = dirname(__DIR__) . '/var/cache/smarty/' . $config->environment;
            $smarty = new Smarty();
            $smarty->setTemplateDir(dirname(__DIR__) . '/templates');
            $smarty->setCompileDir($cacheDirectory . '/compile');
            $smarty->setCacheDir($cacheDirectory . '/cache');
            $smarty->setEscapeHtml(true);
            $smarty->setCaching(Smarty::CACHING_OFF);
            $smarty->setCompileCheck(Smarty::COMPILECHECK_ON);

            return $smarty;
        },
        TemplateRendererInterface::class => static fn (ContainerInterface $container): SmartyRenderer =>
            new SmartyRenderer($container->get(Smarty::class)),
        HomeController::class => static fn (ContainerInterface $container): HomeController => new HomeController(
            $container->get(TemplateRendererInterface::class),
            $container->get(CategoryRepositoryInterface::class),
            $container->get(ArticleRepositoryInterface::class),
        ),
        CategoryController::class => static fn (ContainerInterface $container): CategoryController => new CategoryController(
            $container->get(TemplateRendererInterface::class),
            $container->get(CategoryRepositoryInterface::class),
            $container->get(ArticleRepositoryInterface::class),
            $container->get(AppConfig::class)->articlesPerPage,
        ),
        Router::class => static fn (ContainerInterface $container): Router => new Router(
            [
                '/' => $container->get(HomeController::class),
                '/category' => $container->get(CategoryController::class),
            ],
            $container->get(TemplateRendererInterface::class),
        ),
        RequestHandlerInterface::class => static fn (ContainerInterface $container): HttpKernel => new HttpKernel(
            $container->get(Router::class),
            static function (Throwable $exception): void {
                error_log((string) $exception);
            },
        ),
    ]);
};
