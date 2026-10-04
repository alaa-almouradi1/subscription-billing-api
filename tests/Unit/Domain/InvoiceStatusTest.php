<?php

namespace Tests\Unit\Domain;

use App\Domain\Billing\InvoiceStatus as I;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InvoiceStatusTest extends TestCase
{
    #[DataProvider('transitions')]
    public function test_allowed_transitions(I $from, I $to, bool $allowed): void
    {
        $this->assertSame($allowed, $from->canTransitionTo($to));
    }

    /**
     * @return array<string, array{I, I, bool}>
     */
    public static function transitions(): array
    {
        return [
            'paid' => [I::Open, I::Paid, true],
            'voided' => [I::Open, I::Void, true],
            'written off' => [I::Open, I::Uncollectible, true],
            'late recovery' => [I::Uncollectible, I::Paid, true],
            'paid is final' => [I::Paid, I::Void, false],
            'void is final' => [I::Void, I::Paid, false],
            'cannot reopen' => [I::Uncollectible, I::Open, false],
        ];
    }

    public function test_only_open_and_uncollectible_invoices_are_payable(): void
    {
        $this->assertTrue(I::Open->isPayable());
        $this->assertTrue(I::Uncollectible->isPayable());
        $this->assertFalse(I::Paid->isPayable());
        $this->assertFalse(I::Void->isPayable());
    }
}
