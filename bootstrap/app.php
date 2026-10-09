<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\AuthenticateClient;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\SecurityHeaders;
use App\Support\RequestContext;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend([AssignRequestId::class, ForceHttps::class]);
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'auth.client' => AuthenticateClient::class,
            'admin' => EnsureAdmin::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontReport([ApiException::class]);

        $error = fn (string $code, string $message, int $status, array $extra = []) => response()->json([
            'error' => array_merge([
                'code' => $code,
                'message' => $message,
                'request_id' => RequestContext::id(),
            ], $extra),
        ], $status);

        // Consistent JSON errors for the API. Stack traces / SQL errors are never exposed.
        $exceptions->render(function (Throwable $e, Request $request) use ($error) {
            if (! $request->is('api/*')) {
                return null;
            }

            return match (true) {
                $e instanceof ApiException => $error($e->errorCode, $e->getMessage(), $e->status, $e->details ? ['details' => $e->details] : []),
                $e instanceof ValidationException => $error('VALIDATION_ERROR', 'The request is invalid.', 422, ['details' => $e->errors()]),
                $e instanceof ThrottleRequestsException => $error('RATE_LIMITED', 'Too many requests.', 429),
                $e instanceof AuthenticationException => $error('AUTH_REQUIRED', 'Authentication required.', 401),
                $e instanceof NotFoundHttpException => $error('NOT_FOUND', 'Resource not found.', 404),
                $e instanceof MethodNotAllowedHttpException => $error('METHOD_NOT_ALLOWED', 'Method not allowed.', 405),
                $e instanceof HttpExceptionInterface => $error('HTTP_ERROR', 'Request could not be processed.', $e->getStatusCode()),
                default => $error('INTERNAL_ERROR', 'An internal error occurred.', 500),
            };
        });
    })->create();
