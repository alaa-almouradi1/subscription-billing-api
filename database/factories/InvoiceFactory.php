<?php

namespace Database\Factories;

use App\Domain\Billing\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => 'INV-TEST-'.fake()->unique()->numerify('######'),
            'customer_id' => Customer::factory(),
            'subscription_id' => null,
            'status' => InvoiceStatus::Open,
            'currency' => 'EUR',
            'subtotal' => 1_999,
            'amount_due' => 1_999,
            'issued_at' => now(),
        ];
    }

    public function status(InvoiceStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function amount(int $amount): static
    {
        return $this->state(fn () => ['subtotal' => $amount, 'amount_due' => $amount]);
    }
}
