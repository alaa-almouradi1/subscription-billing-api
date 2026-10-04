<?php

namespace App\Auth;

/**
 * Looks up API clients by key.
 *
 * Only SHA-256 hashes of keys are configured, so a leaked config file or
 * environment dump does not leak usable credentials. Keys are long random
 * strings (see `php artisan billing:client`), which is why a fast hash is
 * enough here; passwords would need a slow one like bcrypt.
 */
class ApiClientRegistry
{
    /**
     * @param  list<array{name?: mixed, key_sha256?: mixed, scopes?: mixed}>  $clients
     */
    public function __construct(private readonly array $clients) {}

    public function find(string $presentedKey): ?ApiClient
    {
        if ($presentedKey === '') {
            return null;
        }

        $presentedHash = hash('sha256', $presentedKey);
        $match = null;

        // Compare against every entry in constant time, without stopping
        // early, so timing does not reveal which entry (if any) matched.
        foreach ($this->clients as $client) {
            $hash = is_string($client['key_sha256'] ?? null) ? strtolower($client['key_sha256']) : '';

            if (strlen($hash) === 64 && hash_equals($hash, $presentedHash) && $match === null) {
                $match = $client;
            }
        }

        if ($match === null || ! is_string($match['name'] ?? null) || ! is_array($match['scopes'] ?? null)) {
            return null;
        }

        return new ApiClient($match['name'], array_values(array_filter($match['scopes'], 'is_string')));
    }

    public static function generateKey(): string
    {
        return 'bk_'.bin2hex(random_bytes(32));
    }
}
