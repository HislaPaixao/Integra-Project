<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
    ->withMiddleware(function (Middleware $middleware) {
        // Este sistema é só API: não existe tela/rota "login" para onde redirecionar.
        // Sem isso, uma chamada sem token quebra com "Route [login] not defined" (erro 500).
        $middleware->redirectGuestsTo(fn (Request $request) => null);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Rotas /api/* sempre respondem JSON (ex.: 401 sem token), mesmo sem o cabeçalho
        // "Accept: application/json".
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
