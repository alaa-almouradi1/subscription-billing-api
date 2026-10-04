<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'default_payment_method' => 'tok_visa',
        ];
    }

    public function withPaymentMethod(string $token): static
    {
        return $this->state(fn () => ['default_payment_method' => $token]);
    }
}
