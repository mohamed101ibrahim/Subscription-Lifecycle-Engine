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
    )    ->withSchedule(function ($schedule) {
        // Expire trials daily at 00:01 UTC
        $schedule->command('subscriptions:expire-trials')
            ->dailyAt('00:01')
            ->withoutOverlapping()
            ->runInBackground();

        // Process grace periods daily at 01:00 UTC
        $schedule->command('subscriptions:process-grace-period')
            ->dailyAt('01:00')
            ->withoutOverlapping()
            ->runInBackground();
    })    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
