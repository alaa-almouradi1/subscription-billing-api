<?php

namespace App\Models;

use App\Domain\Billing\InvoiceStatus;
use App\Domain\IllegalStateTransition;
use App\Domain\Money\Money;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'number',
    'customer_id',
    'subscription_id',
    'status',
    'currency',
    'subtotal',
    'credit_applied',
    'amount_due',
    'period_start',
    'period_end',
    'issued_at',
    'paid_at',
    'voided_at',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'credit_applied' => 0,
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function amountDue(): Money
    {
        return Money::of((int) $this->amount_due, $this->currency);
    }

    public function transitionTo(InvoiceStatus $next): void
    {
        if ($this->status === $next) {
            return;
        }

        if (! $this->status->canTransitionTo($next)) {
            throw IllegalStateTransition::for('invoice', $this->status->value, $next->value);
        }

        $this->status = $next;
    }

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'subtotal' => 'integer',
            'credit_applied' => 'integer',
            'amount_due' => 'integer',
            'period_start' => 'immutable_datetime',
            'period_end' => 'immutable_datetime',
            'issued_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'voided_at' => 'immutable_datetime',
        ];
    }
}
