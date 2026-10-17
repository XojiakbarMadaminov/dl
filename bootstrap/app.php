<?php

use App\Exceptions\ApiException;
use App\Support\ApiErrorResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

        $exceptions->render(function (ApiException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::make(
                    code: $e->getErrorCode(),
                    message: $e->getMessage(),
                    fields: $e->getFields(),
                    status: $e->getStatusCode(),
                );
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::make(
                    code: 'VALIDATION_ERROR',
                    message: $e->getMessage(),
                    fields: $e->errors(),
                    status: $e->status,
                );
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::make(
                    code: 'UNAUTHORIZED',
                    message: $e->getMessage() ?: 'Unauthenticated.',
                    status: 401,
                );
            }
        });

        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::make(
                    code: 'FORBIDDEN',
                    message: $e->getMessage() ?: 'This action is unauthorized.',
                    status: 403,
                );
            }
        });

        $exceptions->render(function (NotFoundHttpException|ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::make(
                    code: 'NOT_FOUND',
                    message: $e->getMessage() ?: 'Resource not found.',
                    status: 404,
                );
            }
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::make(
                    code: 'METHOD_NOT_ALLOWED',
                    message: $e->getMessage() ?: 'Method not allowed.',
                    status: 405,
                );
            }
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $code = match ($e->getStatusCode()) {
                    400 => 'BAD_REQUEST',
                    401 => 'UNAUTHORIZED',
                    403 => 'FORBIDDEN',
                    404 => 'NOT_FOUND',
                    405 => 'METHOD_NOT_ALLOWED',
                    422 => 'VALIDATION_ERROR',
                    429 => 'TOO_MANY_REQUESTS',
                    default => 'HTTP_ERROR',
                };

                return ApiErrorResponse::make(
                    code: $code,
                    message: $e->getMessage() ?: 'An HTTP error occurred.',
                    status: $e->getStatusCode(),
                );
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $status = 500;
                $message = config('app.debug') ? $e->getMessage() : 'An unexpected server error occurred.';

                return ApiErrorResponse::make(
                    code: 'INTERNAL_ERROR',
                    message: $message ?: 'An unexpected server error occurred.',
                    status: $status,
                );
            }
        });
    })->create();
