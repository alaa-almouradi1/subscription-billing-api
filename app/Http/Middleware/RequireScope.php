<?php

namespace App\Http\Middleware;

use App\Auth\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Least privilege: e.g. the dunning service may retry payments and cancel
 * subscriptions, but not create plans. Usage: ->middleware('api.scope:payments.write').
 */
class RequireScope
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $client = $request->attributes->get('api_client');

        if (! $client instanceof ApiClient || ! $client->can($scope)) {
            return response()->json([
                'error' => [
                    'code' => 'insufficient_scope',
                    'message' => "This API key lacks the [{$scope}] scope.",
                ],
            ], 403);
        }

        return $next($request);
    }
}
