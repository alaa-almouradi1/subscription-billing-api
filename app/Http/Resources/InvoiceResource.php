<?php

namespace App\Http\Resources;

use App\Domain\Money\Money;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'customer_id' => $this->customer_id,
            'subscription_id' => $this->subscription_id,
            'subtotal' => Money::of($this->subtotal, $this->currency),
            'credit_applied' => Money::of($this->credit_applied, $this->currency),
            'amount_due' => $this->amountDue(),
            'period_start' => $this->period_start?->toIso8601String(),
            'period_end' => $this->period_end?->toIso8601String(),
            'issued_at' => $this->issued_at->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'voided_at' => $this->voided_at?->toIso8601String(),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn (InvoiceLine $line) => [
                'type' => $line->type,
                'description' => $line->description,
                'amount' => Money::of($line->amount, $this->currency),
            ])),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
