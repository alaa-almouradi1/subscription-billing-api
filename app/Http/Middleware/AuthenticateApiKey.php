<?php

namespace App\Http\Middleware;

use App\Auth\ApiClientRegistry;
use Closure;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates service clients by API key, sent either as `X-Api-Key` or as
 * `Authorization: Bearer <key>` (which is what Prometheus and most HTTP
 * tooling support out of the box).
 *
 * Implementing AuthenticatesRequests places this middleware before
 * ThrottleRequests in Laravel's middleware priority list, so rate limits
 * are counted per authenticated client rather than per IP address.
 */
class AuthenticateApiKey implements AuthenticatesRequests
{
    public function __construct(private readonly ApiClientRegistry $clients) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) ($request->header('X-Api-Key') ?: $request->bearerToken());
        $client = $this->clients->find($key);

        if ($client === null) {
            return response()->json([
                'error' => [
                    'code' => 'unauthenticated',
                    'message' => 'A valid API key is required (X-Api-Key header or Bearer token).',
                ],
            ], 401);
        }

        $request->attributes->set('api_client', $client);
        Context::add('api_client', $client->name);

        return $next($request);
    }
}
