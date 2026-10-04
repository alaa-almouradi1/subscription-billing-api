<?php

namespace App\Models;

use App\Domain\Money\Money;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'email', 'default_payment_method'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'credit_balance' => 0,
    ];

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function creditBalance(string $currency): Money
    {
        if ($this->credit_currency !== null && $this->credit_currency !== $currency) {
            return Money::zero($currency);
        }

        return Money::of((int) $this->credit_balance, $currency);
    }

    protected function casts(): array
    {
        return [
            'credit_balance' => 'integer',
        ];
    }
}
