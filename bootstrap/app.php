<?php

use App\Http\Middleware\EnsureIsAdmin;
use App\Http\Middleware\EnsureNotBlocked;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

/**
 * Point d'entrée de l'application (Laravel 12).
 *
 * Depuis Laravel 11, il n'y a plus de app/Http/Kernel.php : les middlewares et
 * leurs alias se déclarent ici. Breeze ajoute lui-même HandleInertiaRequests
 * lors de son installation ; on le redéclare quand même pour que le fichier
 * reste explicite si l'ordre venait à changer.
 */
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Alias utilisés dans routes/web.php
        $middleware->alias([
            'admin'       => EnsureIsAdmin::class,
            'not.blocked' => EnsureNotBlocked::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
