<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Load application constants
require_once __DIR__.'/../app/Constants.php';

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Middleware global para CORS - deve ser executado primeiro
        $middleware->prepend(\App\Http\Middleware\CorsMiddleware::class);

        $middleware->alias([
            'check.permission' => \App\Http\Middleware\CheckPermission::class,
            'empresa.context' => \App\Http\Middleware\EnsureEmpresaContext::class,
            'cors' => \App\Http\Middleware\CorsMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Tratar exceções de autenticação para APIs PRIMEIRO (antes de RouteNotFoundException)
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            // Código de debug removido - arquivo de log não existe
            // Sempre retornar JSON para rotas API (mesmo que expectsJson seja false)
            if (str_starts_with($request->path(), 'api')) {
                return response()->json(['error' => 'Não autenticado', 'message' => 'Usuário não autenticado!'], 401);
            }
        });
        
        $exceptions->render(function (\Symfony\Component\Routing\Exception\RouteNotFoundException $e, \Illuminate\Http\Request $request) {
            // Sempre retornar JSON para rotas API (mesmo que expectsJson seja false)
            if (str_starts_with($request->path(), 'api')) {
                // Se a mensagem contém "Route [login]", é uma tentativa de redirecionamento de autenticação
                if (str_contains($e->getMessage(), 'Route [login]')) {
                    return response()->json(['error' => 'Não autenticado', 'message' => 'Usuário não autenticado!'], 401);
                }
                return response()->json(['error' => 'Rota não encontrada', 'message' => $e->getMessage()], 404);
            }
        });
    })->create();
