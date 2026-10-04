<?php

namespace App\Models;

use App\Domain\Billing\BillingInterval;
use App\Domain\Money\Money;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'amount', 'currency', 'interval', 'trial_days', 'active'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory, HasUuids;

    public function price(): Money
    {
        return Money::of((int) $this->amount, $this->currency);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'interval' => BillingInterval::class,
            'trial_days' => 'integer',
            'active' => 'boolean',
        ];
    }
}
