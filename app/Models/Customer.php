<?php

namespace App\Models;

use App\Domain\Money\CurrencyMismatch;
use App\Domain\Money\Money;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

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

    public function addCredit(Money $amount): void
    {
        if ($this->credit_currency !== null && $this->credit_currency !== $amount->currency) {
            throw CurrencyMismatch::between($this->credit_currency, $amount->currency);
        }

        $this->credit_currency = $amount->currency;
        $this->credit_balance = (int) $this->credit_balance + $amount->amount;
    }

    public function deductCredit(Money $amount): void
    {
        $remaining = $this->creditBalance($amount->currency)->subtract($amount);

        if ($remaining->isNegative()) {
            throw new LogicException('Cannot deduct more credit than the customer has.');
        }

        $this->credit_balance = $remaining->amount;
        $this->credit_currency = $remaining->isZero() ? null : $amount->currency;
    }

    protected function casts(): array
    {
        return [
            'credit_balance' => 'integer',
        ];
    }
}
