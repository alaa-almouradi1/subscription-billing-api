<?php

namespace Tests\Unit\Domain;

use App\Domain\Billing\SubscriptionStatus as S;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SubscriptionStatusTest extends TestCase
{
    #[DataProvider('transitions')]
    public function test_allowed_transitions(S $from, S $to, bool $allowed): void
    {
        $this->assertSame($allowed, $from->canTransitionTo($to));
    }

    /**
     * @return array<string, array{S, S, bool}>
     */
    public static function transitions(): array
    {
        return [
            'trial converts' => [S::Trialing, S::Active, true],
            'trial payment fails' => [S::Trialing, S::PastDue, true],
            'payment fails' => [S::Active, S::PastDue, true],
            'recovered by dunning' => [S::PastDue, S::Active, true],
            'dunning exhausted' => [S::PastDue, S::Canceled, true],
            'cannot go back to trial' => [S::Active, S::Trialing, false],
            'canceled is terminal' => [S::Canceled, S::Active, false],
            'no self transition' => [S::Active, S::Active, false],
        ];
    }

    public function test_only_canceled_subscriptions_stop_renewing(): void
    {
        foreach (S::cases() as $status) {
            $this->assertSame($status !== S::Canceled, $status->renews());
        }
    }
}
