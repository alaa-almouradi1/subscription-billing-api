<?php

namespace App\Domain\Money;

use InvalidArgumentException;
use JsonSerializable;
use OverflowException;

/**
 * An immutable amount of money in minor units (e.g. cents).
 *
 * Amounts are integers on purpose: floats cannot represent most decimal
 * fractions exactly, and rounding errors in a billing system turn into
 * real money. See docs/adr/0001-money-as-integer-minor-units.md.
 */
final class Money implements JsonSerializable
{
    private function __construct(
        public readonly int $amount,
        public readonly string $currency,
    ) {}

    public static function of(int $amount, string $currency): self
    {
        $currency = strtoupper($currency);

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException("Invalid ISO 4217 currency code [{$currency}].");
        }

        return new self($amount, $currency);
    }

    public static function zero(string $currency): self
    {
        return self::of(0, $currency);
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function negate(): self
    {
        return new self(-$this->amount, $this->currency);
    }

    /**
     * Multiply by numerator / denominator, rounding half away from zero.
     *
     * Used for proration, where the ratio is "seconds remaining / seconds in
     * period". Integer arithmetic keeps the result exact until the single,
     * explicit rounding step.
     */
    public function multiplyByRatio(int $numerator, int $denominator): self
    {
        if ($denominator <= 0) {
            throw new InvalidArgumentException('Denominator must be positive.');
        }

        if ($numerator < 0) {
            throw new InvalidArgumentException('Numerator must not be negative.');
        }

        $product = $this->amount * $numerator;

        if (! is_int($product)) {
            throw new OverflowException('Amount is too large to multiply safely.');
        }

        $sign = $product < 0 ? -1 : 1;
        $absolute = abs($product);
        $quotient = intdiv($absolute, $denominator);

        if (($absolute % $denominator) * 2 >= $denominator) {
            $quotient++;
        }

        return new self($sign * $quotient, $this->currency);
    }

    public function min(Money $other): self
    {
        $this->assertSameCurrency($other);

        return $this->amount <= $other->amount ? $this : $other;
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    public function equals(Money $other): bool
    {
        return $this->currency === $other->currency && $this->amount === $other->amount;
    }

    /**
     * @return array{amount: int, currency: string}
     */
    public function jsonSerialize(): array
    {
        return ['amount' => $this->amount, 'currency' => $this->currency];
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw CurrencyMismatch::between($this->currency, $other->currency);
        }
    }
}
