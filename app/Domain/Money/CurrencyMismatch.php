<?php

namespace App\Domain\Money;

use App\Domain\BillingException;

final class CurrencyMismatch extends BillingException
{
    public static function between(string $left, string $right): self
    {
        return new self("Cannot combine amounts in {$left} and {$right}.");
    }

    public function errorCode(): string
    {
        return 'currency_mismatch';
    }

    public function status(): int
    {
        return 422;
    }
}
