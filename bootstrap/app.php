<?php

use App\Exceptions\BusinessRuleException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('reservations:expire')
            ->everyMinute()
            ->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') || $request->expectsJson() ? null : route('login')
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $status = 500;
                $message = 'Internal Server Error';
                $errors = null;

                if ($e instanceof AuthenticationException) {
                    $status = 401;
                    $message = 'Unauthenticated.';
                } elseif ($e instanceof ValidationException) {
                    $status = 422;
                    $message = 'Validation failed.';
                    $errors = $e->errors();
                } elseif ($e instanceof AccessDeniedHttpException || $e instanceof UnauthorizedException) {
                    $status = 403;
                    $message = 'This action is unauthorized.';
                } elseif ($e instanceof NotFoundHttpException || $e instanceof ModelNotFoundException) {
                    $status = 404;
                    $message = 'Resource not found.';
                } elseif ($e instanceof BusinessRuleException) {
                    $status = 422;
                    $message = $e->getMessage();
                } elseif ($e instanceof HttpException) {
                    $status = $e->getStatusCode();
                    $message = $e->getMessage() ?: 'HTTP Error';
                }

                $response = [
                    'success' => false,
                    'message' => $message,
                ];

                if (! is_null($errors)) {
                    $response['errors'] = $errors;
                }

                return response()->json($response, $status);
            }
        });
    })->create();

if (PHP_OS_FAMILY === 'Windows' && ! file_exists('/.dockerenv') && file_exists(dirname(__DIR__).'/.env.windows') && ! $app->runningUnitTests()) {
    $app->loadEnvironmentFrom('.env.windows');
}

return $app;
