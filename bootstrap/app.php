<?php

use App\Http\Middleware\LoadAppearanceFromUser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\SetUserLocale;
use App\Http\Middleware\EnsureCompanySelected;
use App\Http\Middleware\EnsureCompanyRole;
use App\Http\Middleware\EnsureCompanyRoleAny;
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
        // '*' confía en el header X-Forwarded-Proto que mande CUALQUIERA (no
        // solo un proxy real) -- en local no hay proxy en frente, así que un
        // navegador/extensión que lo mande hace que Laravel genere URLs
        // https:// (route()/url()) aunque el sitio esté sirviéndose por http,
        // y el navegador bloquea la descarga como "redirected through an
        // insecure connection". Solo se confía en cualquier proxy en
        // producción (detrás de un balanceador/CDN real).
        // Vacío ([]): no se confía en ningún proxy. IMPORTANTE: env() todavía
        // no está disponible en este punto del bootstrap (siempre devuelve
        // null acá, antes de que .env termine de cargar), así que no se puede
        // condicionar esto por ambiente aquí -- y "confiar en '*'" (cualquier
        // IP) ya es de por sí una mala práctica que la propia documentación
        // de Laravel desaconseja: cualquiera puede mandar X-Forwarded-Proto:
        // https y forzar que route()/url() generen enlaces https:// aunque el
        // sitio se sirva por http, lo que el navegador bloquea como descarga
        // insegura. Si algún día hay un proxy real en producción (load
        // balancer/CDN), hay que poner acá su(s) IP(s) exacta(s), no '*'.
        $middleware->trustProxies(at: []);
        $middleware->web(SetUserLocale::class);
        $middleware->alias([
            'company.selected' => EnsureCompanySelected::class,
            'company.role' => EnsureCompanyRole::class,
            'company.role.any' => EnsureCompanyRoleAny::class,
            'company.owner' => EnsureCompanyOwner::class,
            'superadmin' => EnsureSuperadmin::class,
            'company.api_token' => AuthenticateCompanyApiToken::class,
        ]);
        $middleware->web(LoadAppearanceFromUser::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
