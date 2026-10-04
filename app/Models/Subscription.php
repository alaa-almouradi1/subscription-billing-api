<?php

namespace App\Models;

use App\Domain\Billing\SubscriptionStatus;
use App\Domain\IllegalStateTransition;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'customer_id',
    'plan_id',
    'status',
    'billing_cycle_anchor',
    'periods_billed',
    'current_period_start',
    'current_period_end',
    'trial_ends_at',
    'cancel_at_period_end',
    'canceled_at',
])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'periods_billed' => 0,
        'cancel_at_period_end' => false,
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasOne<Invoice, $this>
     */
    public function latestInvoice(): HasOne
    {
        return $this->hasOne(Invoice::class)->latestOfMany(['issued_at', 'id']);
    }

    /**
     * Move to a new status, enforcing the lifecycle rules.
     * Re-applying the current status is a no-op so event handlers stay idempotent.
     */
    public function transitionTo(SubscriptionStatus $next): void
    {
        if ($this->status === $next) {
            return;
        }

        if (! $this->status->canTransitionTo($next)) {
            throw IllegalStateTransition::for('subscription', $this->status->value, $next->value);
        }

        $this->status = $next;
    }

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'billing_cycle_anchor' => 'immutable_datetime',
            'periods_billed' => 'integer',
            'current_period_start' => 'immutable_datetime',
            'current_period_end' => 'immutable_datetime',
            'trial_ends_at' => 'immutable_datetime',
            'cancel_at_period_end' => 'boolean',
            'canceled_at' => 'immutable_datetime',
        ];
    }
}
