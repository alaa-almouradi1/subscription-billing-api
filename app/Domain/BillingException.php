<?php

namespace App\Domain;

use RuntimeException;

/**
 * Base class for expected, business-level failures.
 *
 * Each subclass carries a stable machine-readable error code and the HTTP
 * status the API should answer with, so controllers never need to map
 * domain errors by hand.
 */
abstract class BillingException extends RuntimeException
{
    abstract public function errorCode(): string;

    public function status(): int
    {
        return 409;
    }
}
