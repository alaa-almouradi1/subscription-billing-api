<?php

use App\Domain\BillingException;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\RequireScope;
use App\Http\Middleware\SetSecurityHeaders;
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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->append(SetSecurityHeaders::class);

        // Behind a load balancer the client IP (used for rate limits and logs)
        // comes from X-Forwarded-For, which must only be trusted from the proxy.
        $proxies = env('TRUSTED_PROXIES');
        if (is_string($proxies) && $proxies !== '') {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        $middleware->alias([
            'api.key' => AuthenticateApiKey::class,
            'api.scope' => RequireScope::class,
            'idempotent' => EnsureIdempotency::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Business-rule violations are expected outcomes, not bugs:
        // answer with a stable error code and keep them out of error logs.
        $exceptions->dontReport([BillingException::class]);

        $exceptions->render(fn (BillingException $e) => response()->json([
            'error' => [
                'code' => $e->errorCode(),
                'message' => $e->getMessage(),
            ],
        ], $e->status()));
    })->create();
