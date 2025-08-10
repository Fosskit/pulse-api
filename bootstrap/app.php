<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\ApiVersionMiddleware;
use App\Http\Middleware\ApiMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register API-specific middleware
        $middleware->alias([
            'api.version' => ApiVersionMiddleware::class,
            'api.middleware' => ApiMiddleware::class,
        ]);

        // Configure API middleware stack
        $middleware->api(prepend: [

        ]);

        // Configure throttling for different API endpoints
        $middleware->throttleApi('api');

        // Configure CORS for API routes
        $middleware->web(append: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Configure API exception handling
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->is('api/*')) {
                return app(\App\Exceptions\ApiExceptionHandler::class)->render($request, $e);
            }
        });
    })->create();
