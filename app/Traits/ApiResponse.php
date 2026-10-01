<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // Intercept all API exceptions
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {

                // 1. Validation Exceptions (422)
                if ($e instanceof ValidationException) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $e->errors(),
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                // 2. Authentication Exceptions (401)
                if ($e instanceof AuthenticationException) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthenticated or invalid token',
                    ], Response::HTTP_UNAUTHORIZED);
                }

                // 3. 404 Not Found Exceptions
                if ($e instanceof NotFoundHttpException) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Resource or endpoint not found',
                    ], Response::HTTP_NOT_FOUND);
                }

                // 4. HttpExceptions (e.g., abort(403), abort(429))
                if ($e instanceof HttpExceptionInterface) {
                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage() ?: 'An HTTP error occurred',
                    ], $e->getStatusCode());
                }

                // 5. Catch-All for Uncaught 500 Server Errors & Critical Failures
                Log::critical('API 500 Error: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'url' => $request->fullUrl(),
                ]);

                $isDebug = config('app.debug', false);

                return response()->json([
                    'success' => false,
                    'message' => $isDebug ? $e->getMessage() : 'A server error occurred. Please try again later.',
                    'errors' => $isDebug ? [
                        'exception' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ] : null,
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        });
    })->create();
