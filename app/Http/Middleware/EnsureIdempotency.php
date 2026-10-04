<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes unsafe requests safe to retry with an Idempotency-Key header.
 *
 *  - first request:          runs normally; the response is stored
 *  - retry, same request:    the stored response is replayed, nothing runs
 *  - retry while running:    409, try again shortly
 *  - same key, other body:   422, the client has a bug
 *
 * 5xx responses are not stored, so a retry after a server error runs again.
 * Usage: ->middleware('idempotent') or ->middleware('idempotent:required').
 */
class EnsureIdempotency
{
    private const TTL_HOURS = 24;

    public function handle(Request $request, Closure $next, string $mode = 'optional'): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');

        if ($key === null || $key === '') {
            return $mode === 'required'
                ? $this->error(400, 'idempotency_key_required', 'This endpoint requires an Idempotency-Key header.')
                : $next($request);
        }

        if (strlen($key) > 255) {
            return $this->error(400, 'idempotency_key_invalid', 'The Idempotency-Key header must be at most 255 characters.');
        }

        $scope = hash('sha256', (string) $request->header('X-Api-Key'));
        $fingerprint = hash('sha256', $request->method().' '.$request->path().' '.$request->getContent());

        try {
            $record = IdempotencyKey::create([
                'scope' => $scope,
                'idempotency_key' => $key,
                'fingerprint' => $fingerprint,
                'status' => IdempotencyKey::STATUS_PROCESSING,
                'expires_at' => now()->addHours(self::TTL_HOURS),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->replay($scope, $key, $fingerprint);
        }

        $response = $next($request);

        if ($response->getStatusCode() >= 500) {
            $record->delete();
        } else {
            $record->update([
                'status' => IdempotencyKey::STATUS_COMPLETED,
                'response_status' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
            ]);
        }

        return $response;
    }

    private function replay(string $scope, string $key, string $fingerprint): Response
    {
        $record = IdempotencyKey::query()
            ->where('scope', $scope)
            ->where('idempotency_key', $key)
            ->first();

        if ($record === null) {
            // Deleted between our insert attempt and this read (5xx or prune).
            return $this->error(409, 'idempotency_request_in_progress', 'Please retry the request.');
        }

        if (! hash_equals($record->fingerprint, $fingerprint)) {
            return $this->error(422, 'idempotency_key_reused', 'This Idempotency-Key was already used for a different request.');
        }

        if ($record->status === IdempotencyKey::STATUS_PROCESSING) {
            return $this->error(409, 'idempotency_request_in_progress', 'A request with this Idempotency-Key is still being processed.')
                ->header('Retry-After', '1');
        }

        return response((string) $record->response_body, (int) $record->response_status, [
            'Content-Type' => 'application/json',
            'Idempotent-Replayed' => 'true',
        ]);
    }

    private function error(int $status, string $code, string $message): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
