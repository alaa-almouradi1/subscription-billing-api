<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = (string) $request->header('X-Api-Key', '');

        foreach (config('billing.api_keys', []) as $key) {
            // hash_equals() compares in constant time, so response timing
            // does not leak how much of a guessed key was correct.
            if ($provided !== '' && hash_equals($key, $provided)) {
                return $next($request);
            }
        }

        return response()->json([
            'error' => [
                'code' => 'unauthenticated',
                'message' => 'A valid X-Api-Key header is required.',
            ],
        ], 401);
    }
}
