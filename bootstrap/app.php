<?php

use App\Http\Middleware\AddMobileApiHeaders;
use App\Http\Middleware\EnsureMobileApiAccess;
use App\Http\Middleware\TouchMobileApiToken;
use App\Support\Api\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.access' => EnsureMobileApiAccess::class,
            'api.token.touch' => TouchMobileApiToken::class,
            'api.version' => AddMobileApiHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, \Throwable $exception): bool =>
                $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (
            ValidationException $exception,
            Request $request,
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                'تعذر قبول البيانات المرسلة.',
                'validation_failed',
                422,
                $exception->errors(),
            );
        });

        $exceptions->render(function (
            AuthenticationException $exception,
            Request $request,
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                'يجب تسجيل الدخول للوصول إلى هذه الخدمة.',
                'unauthenticated',
                401,
            );
        });

        $exceptions->render(function (
            AuthorizationException $exception,
            Request $request,
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                'لا تملك صلاحية تنفيذ هذه العملية.',
                'forbidden',
                403,
            );
        });

        $exceptions->render(function (
            ModelNotFoundException $exception,
            Request $request,
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                'السجل المطلوب غير موجود.',
                'not_found',
                404,
            );
        });

        $exceptions->render(function (
            HttpException $exception,
            Request $request,
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $exception->getStatusCode();
            $message = $status >= 500
                ? 'حدث خطأ غير متوقع في الخادم.'
                : ($exception->getMessage() ?: 'تعذر تنفيذ الطلب.');

            return ApiResponse::error(
                $message,
                'http_'.$status,
                $status,
            );
        });
    })->create();
