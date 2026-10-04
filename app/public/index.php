<?php

declare(strict_types=1);

use App\Config\AppConfig;
use App\Http\Request;
use App\Http\RequestHandlerInterface;

ini_set('display_errors', '0');

// This fallback also handles failures before the container can be created.
set_exception_handler(static function (Throwable $exception): void {
    error_log((string) $exception);
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
        echo 'Внутренняя ошибка сервера.';
    }
});

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

$container = require dirname(__DIR__) . '/config/bootstrap.php';
date_default_timezone_set($container->get(AppConfig::class)->timezone);

$request = Request::fromServer($_SERVER, $_GET);
$container->get(RequestHandlerInterface::class)->handle($request)->send();
