<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Mensajes en español para los errores HTTP de la API
        $apiError = fn (Request $request, string $message, int $status) => $request->is('api/*')
            ? response()->json(['message' => $message], $status)
            : null;

        $exceptions->render(fn (AuthenticationException $e, Request $request) =>
            $apiError($request, 'Tu sesión expiró o no has iniciado sesión.', 401));

        $exceptions->render(fn (AccessDeniedHttpException $e, Request $request) =>
            $apiError($request, 'No tienes permiso para realizar esta acción.', 403));

        // Incluye los modelos no encontrados y los registros de otro usuario (ensureOwner)
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) =>
            $apiError($request, 'El registro solicitado no existe.', 404));

        $exceptions->render(fn (MethodNotAllowedHttpException $e, Request $request) =>
            $apiError($request, 'Método no permitido para esta ruta.', 405));

        $exceptions->render(fn (TooManyRequestsHttpException $e, Request $request) =>
            $apiError($request, 'Demasiadas solicitudes. Espera un momento e inténtalo de nuevo.', 429));

        // Errores inesperados: en producción no se expone el detalle
        $exceptions->render(function (\Throwable $e, Request $request) use ($apiError) {
            if ($e instanceof ValidationException || $e instanceof HttpResponseException
                || $e instanceof HttpExceptionInterface || config('app.debug')) {
                return null;
            }

            return $apiError($request, 'Ocurrió un error en el servidor. Inténtalo más tarde.', 500);
        });
    })->create();
