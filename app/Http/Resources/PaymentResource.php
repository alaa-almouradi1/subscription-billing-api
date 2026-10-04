<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'amount' => $this->money(),
            'status' => $this->status->value,
            'psp_reference' => $this->psp_reference,
            'failure_code' => $this->failure_code,
            'failure_message' => $this->failure_message,
            'created_at' => $this->created_at?->toIso8601String(),
            'settled_at' => $this->settled_at?->toIso8601String(),
        ];
    }
}
