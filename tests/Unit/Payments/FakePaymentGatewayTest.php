<?php

namespace Tests\Unit\Payments;

use App\Domain\Money\Money;
use App\Domain\Payments\ChargeRequest;
use App\Domain\Payments\GatewayUnavailable;
use App\Domain\Payments\PaymentStatus;
use App\Infrastructure\Payments\FakePaymentGateway;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FakePaymentGatewayTest extends TestCase
{
    private function request(string $token, string $id = 'pay_1'): ChargeRequest
    {
        return new ChargeRequest($id, Money::of(1_000, 'EUR'), $token, 'Test charge');
    }

    #[DataProvider('tokens')]
    public function test_tokens_produce_deterministic_outcomes(string $token, PaymentStatus $status, ?string $failureCode): void
    {
        $result = (new FakePaymentGateway)->charge($this->request($token));

        $this->assertSame($status, $result->status);
        $this->assertSame($failureCode, $result->failureCode);
    }

    /**
     * @return array<string, array{string, PaymentStatus, ?string}>
     */
    public static function tokens(): array
    {
        return [
            'success' => ['tok_visa', PaymentStatus::Succeeded, null],
            'declined' => ['tok_chargeDeclined', PaymentStatus::Failed, 'card_declined'],
            'no funds' => ['tok_insufficientFunds', PaymentStatus::Failed, 'insufficient_funds'],
            'pending' => ['tok_pending', PaymentStatus::Processing, null],
            'unknown' => ['tok_nope', PaymentStatus::Failed, 'invalid_payment_method'],
        ];
    }

    public function test_repeating_a_payment_id_does_not_charge_twice(): void
    {
        $gateway = new FakePaymentGateway;

        $first = $gateway->charge($this->request('tok_visa'));
        $second = $gateway->charge($this->request('tok_visa'));

        $this->assertSame($first, $second);
        $this->assertCount(1, $gateway->charges());
    }

    public function test_timeouts_surface_as_unknown_outcome(): void
    {
        $this->expectException(GatewayUnavailable::class);

        (new FakePaymentGateway)->charge($this->request('tok_timeout'));
    }
}
