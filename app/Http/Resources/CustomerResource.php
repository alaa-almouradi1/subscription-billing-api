<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'has_payment_method' => $this->default_payment_method !== null,
            'credit_balance' => $this->credit_currency === null
                ? null
                : $this->creditBalance($this->credit_currency),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
