<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withSingletons([
        ConsoleKernel::class => \App\Console\Kernel::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        // Apache (.htaccess) ya maneja CORS — evita header duplicado
        $middleware->remove(\Illuminate\Http\Middleware\HandleCors::class);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        $middleware->appendToGroup('api', [
            \App\Http\Middleware\ForceApiJsonResponse::class,
            \App\Http\Middleware\ApiRequestContext::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Error de validacion.',
                'data' => null,
                'errors' => $exception->errors(),
                'meta' => [
                    'request_id' => $request->attributes->get('request_id'),
                ],
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'No autenticado.',
                'data' => null,
                'errors' => [
                    'auth' => ['Debes iniciar sesion para acceder a este recurso.'],
                ],
                'meta' => [
                    'request_id' => $request->attributes->get('request_id'),
                ],
            ], 401);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'No autorizado para este recurso.',
                'data' => null,
                'errors' => [
                    'authorization' => ['No autorizado para este recurso.'],
                ],
                'meta' => [
                    'request_id' => $request->attributes->get('request_id'),
                ],
            ], 403);
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Recurso no encontrado.',
                'data' => null,
                'errors' => [
                    'resource' => ['El recurso solicitado no existe.'],
                ],
                'meta' => [
                    'request_id' => $request->attributes->get('request_id'),
                ],
            ], 404);
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Recurso no encontrado.',
                'data' => null,
                'errors' => [
                    'resource' => ['El recurso solicitado no existe.'],
                ],
                'meta' => [
                    'request_id' => $request->attributes->get('request_id'),
                ],
            ], 404);
        });

        $exceptions->render(function (TooManyRequestsHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Demasiadas solicitudes. Intenta de nuevo más tarde.',
                'data' => null,
                'errors' => [
                    'throttle' => ['Demasiadas solicitudes. Intenta de nuevo más tarde.'],
                ],
                'meta' => [
                    'request_id' => $request->attributes->get('request_id'),
                ],
            ], 429);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage() !== '' ? $exception->getMessage() : 'Error HTTP.',
                'data' => null,
                'errors' => [
                    'http' => [$exception->getMessage() !== '' ? $exception->getMessage() : 'Error HTTP.'],
                ],
                'meta' => [
                    'request_id' => $request->attributes->get('request_id'),
                ],
            ], $exception->getStatusCode());
        });

        $exceptions->render(function (\Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $message = config('app.debug')
                ? $exception->getMessage()
                : 'Ocurrio un error interno en el servidor.';

            Log::channel('api')->error('Unhandled API exception', [
                'request_id' => $request->attributes->get('request_id'),
                'path' => $request->path(),
                'method' => $request->method(),
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor.',
                'data' => null,
                'errors' => [
                    'server' => [$message],
                ],
                'meta' => [
                    'request_id' => $request->attributes->get('request_id'),
                ],
            ], 500);
        });
    })->create();
