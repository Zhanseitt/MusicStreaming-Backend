<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Дополнительные middleware для web-группы
        $middleware->web(append: [
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Если нужно, сюда можно добавить middleware для API:
        // $middleware->api(append: [
        //     ...
        // ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Здесь можно настроить обработку исключений при необходимости
    })
    ->create();
