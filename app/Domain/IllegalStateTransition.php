<?php

namespace App\Domain;

final class IllegalStateTransition extends BillingException
{
    public static function for(string $entity, string $from, string $to): self
    {
        return new self("A {$entity} cannot move from [{$from}] to [{$to}].");
    }

    public function errorCode(): string
    {
        return 'invalid_state_transition';
    }
}
