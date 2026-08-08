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
        // getenv('K_SERVICE') (no env(), que todavía no está disponible en
        // este punto del bootstrap porque .env no terminó de cargar) detecta
        // si el proceso corre dentro de Cloud Run: Google inyecta esa
        // variable de entorno automáticamente en el contenedor, y no viene
        // del .env. En Cloud Run el contenedor sólo es alcanzable a través
        // del proxy de Google (que reescribe/sanitiza cualquier
        // X-Forwarded-* que mande el cliente), así que ahí sí es seguro
        // confiar en '*'. Fuera de Cloud Run (local, otros hosts) no se
        // confía en ningún proxy.
        $middleware->trustProxies(at: getenv('K_SERVICE') ? '*' : []);
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
