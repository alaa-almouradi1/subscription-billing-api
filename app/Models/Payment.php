<?php

namespace App\Models;

use App\Domain\Money\Money;
use App\Domain\Payments\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'amount',
    'currency',
    'status',
    'payment_method',
    'psp_reference',
    'failure_code',
    'failure_message',
    'settled_at',
])]
class Payment extends Model
{
    use HasUuids;

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function money(): Money
    {
        return Money::of((int) $this->amount, $this->currency);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => PaymentStatus::class,
            'settled_at' => 'immutable_datetime',
        ];
    }
}
