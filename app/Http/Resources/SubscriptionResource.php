<?php

namespace App\Http\Resources;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'plan' => PlanResource::make($this->whenLoaded('plan')),
            'status' => $this->status->value,
            'current_period_start' => $this->current_period_start->toIso8601String(),
            'current_period_end' => $this->current_period_end->toIso8601String(),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'cancel_at_period_end' => $this->cancel_at_period_end,
            'canceled_at' => $this->canceled_at?->toIso8601String(),
            'latest_invoice' => InvoiceResource::make($this->whenLoaded('latestInvoice')),
        ];
    }
}
