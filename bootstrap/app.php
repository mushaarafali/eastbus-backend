<?php

use App\Http\Middleware\PassengerApiAuth;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\StaffApiAuth;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(
    basePath: dirname(__DIR__)
)
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // Trust Railway reverse proxy so Laravel detects HTTPS correctly.
        $middleware->trustProxies(
            at: '*'
        );

        $middleware->alias([
            'staff.api' => StaffApiAuth::class,
            'passenger.api' => PassengerApiAuth::class,
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();