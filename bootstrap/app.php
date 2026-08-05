<?php

use App\Http\Middleware\LoadAppearanceFromUser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\SetUserLocale;
use App\Http\Middleware\EnsureCompanySelected;
use App\Http\Middleware\EnsureCompanyRole;
use App\Http\Middleware\EnsureCompanyOwner;
use App\Http\Middleware\EnsureSuperadmin;
use App\Http\Middleware\AuthenticateCompanyApiToken;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(SetUserLocale::class);
        $middleware->alias([
            'company.selected' => EnsureCompanySelected::class,
            'company.role' => EnsureCompanyRole::class,
            'company.owner' => EnsureCompanyOwner::class,
            'superadmin' => EnsureSuperadmin::class,
            'company.api_token' => AuthenticateCompanyApiToken::class,
        ]);
        $middleware->web(LoadAppearanceFromUser::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
