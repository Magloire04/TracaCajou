<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Illuminate\Session\Middleware\StartSession::class,
        ]);
        $middleware->alias(['cooperative.access' => \App\Http\Middleware\VerifyCooperativeAccess::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $_e, $_request) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Ressource introuvable.', 'status' => 404],
            ], 404);
        });

        $exceptions->render(function (\RuntimeException $e, $_request) {
            // Let Symfony HttpExceptions (abort(4xx/5xx)) pass through to Laravel's default handler
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                return null;
            }
            \Illuminate\Support\Facades\Log::channel('security')->error('RUNTIME_ERROR', [
                'message' => $e->getMessage(),
            ]);
            return response()->json([
                'error' => ['code' => 'SERVER_ERROR', 'message' => 'Une erreur interne est survenue.', 'status' => 500],
            ], 500);
        });
    })->create();
