<?php

namespace App\Auth;

/**
 * A service that calls this API, identified by its API key.
 */
final class ApiClient
{
    /**
     * @param  list<string>  $scopes  e.g. ["billing.read", "payments.write"], or ["*"]
     */
    public function __construct(
        public readonly string $name,
        public readonly array $scopes,
    ) {}

    public function can(string $scope): bool
    {
        return in_array('*', $this->scopes, true) || in_array($scope, $this->scopes, true);
    }
}
