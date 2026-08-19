<?php

use App\Http\Middleware\AuthorizeAction;
use App\Http\Middleware\AuthorizePage;
use App\Http\Middleware\EnsureSingleDevice;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'single-device' => EnsureSingleDevice::class,
            'page' => AuthorizePage::class,
            'action' => AuthorizeAction::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
