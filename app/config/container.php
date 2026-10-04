<?php

declare(strict_types=1);

use App\Config\AppConfig;
use App\Config\ConfigLoader;
use App\Config\DatabaseConfig;
use App\Container\Container;
use App\Container\ContainerInterface;
use App\Controller\HomeController;
use App\Http\HttpKernel;
use App\Http\RequestHandlerInterface;
use App\Http\Router;
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
        HomeController::class => static fn (ContainerInterface $container): HomeController =>
            new HomeController($container->get(TemplateRendererInterface::class)),
        Router::class => static fn (ContainerInterface $container): Router => new Router(
            ['/' => $container->get(HomeController::class)],
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
