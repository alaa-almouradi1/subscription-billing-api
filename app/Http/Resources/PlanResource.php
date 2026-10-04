<?php

namespace App\Http\Resources;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plan
 */
class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'price' => $this->price(),
            'interval' => $this->interval->value,
            'trial_days' => $this->trial_days,
            'active' => $this->active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
