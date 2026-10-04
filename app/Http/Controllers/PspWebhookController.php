<?php

namespace App\Http\Controllers;

use App\Domain\Payments\WebhookSignature;
use App\Services\Payments\WebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PspWebhookController extends Controller
{
    public function __invoke(Request $request, WebhookProcessor $processor): JsonResponse
    {
        // Verify against the raw body: re-encoding parsed JSON can change
        // bytes (key order, escaping) and break the signature.
        $valid = WebhookSignature::verify(
            $request->getContent(),
            (string) $request->header('Psp-Signature'),
            (string) config('billing.webhooks.secret'),
            now()->getTimestamp(),
            (int) config('billing.webhooks.tolerance'),
        );

        if (! $valid) {
            return response()->json(['error' => ['code' => 'invalid_signature', 'message' => 'Invalid webhook signature.']], 400);
        }

        $event = Validator::make($request->json()->all(), [
            'id' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:64'],
            'data' => ['required', 'array'],
            'data.payment_id' => ['required', 'string', 'max:255'],
            // validate() returns only keys that have a rule, so every field
            // the processor reads must be listed here or it is dropped.
            'data.psp_reference' => ['nullable', 'string', 'max:255'],
            'data.failure_code' => ['nullable', 'string', 'max:64'],
            'data.failure_message' => ['nullable', 'string', 'max:255'],
        ])->validate();

        return response()->json(['status' => $processor->process($event)]);
    }
}
