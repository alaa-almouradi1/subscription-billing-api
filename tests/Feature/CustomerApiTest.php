<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_can_be_created(): void
    {
        $response = $this->postJson('/api/v1/customers', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'payment_method' => 'tok_visa',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'ada@example.com')
            ->assertJsonPath('data.has_payment_method', true);

        $this->assertDatabaseHas('customers', [
            'id' => $response->json('data.id'),
            'default_payment_method' => 'tok_visa',
        ]);
    }

    public function test_the_payment_token_is_never_exposed(): void
    {
        $customer = Customer::factory()->create();

        $this->getJson("/api/v1/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.default_payment_method');
    }

    public function test_emails_must_be_unique(): void
    {
        Customer::factory()->create(['email' => 'ada@example.com']);

        $this->postJson('/api/v1/customers', ['name' => 'Ada', 'email' => 'ada@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_the_payment_method_can_be_updated(): void
    {
        $customer = Customer::factory()->withPaymentMethod('tok_chargeDeclined')->create();

        $this->patchJson("/api/v1/customers/{$customer->id}", ['payment_method' => 'tok_visa'])
            ->assertOk();

        $this->assertSame('tok_visa', $customer->fresh()->default_payment_method);
    }
}
