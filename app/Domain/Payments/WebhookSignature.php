<?php

namespace App\Domain\Payments;

/**
 * HMAC-SHA256 webhook signatures in the widely used "t=...,v1=..." format.
 *
 *   Psp-Signature: t=1767225600,v1=5257a869e7ecebeda32affa62cdca3fa51cad7e77a0e56ff536d0ce8e108d8bd
 *
 * The timestamp is part of the signed payload, which lets the receiver
 * reject replays of old (but validly signed) requests. Several v1 values
 * may be present while the provider rotates its secret.
 */
final class WebhookSignature
{
    public static function header(string $payload, string $secret, int $timestamp): string
    {
        return "t={$timestamp},v1=".self::compute($payload, $secret, $timestamp);
    }

    public static function verify(string $payload, string $header, string $secret, int $now, int $toleranceSeconds = 300): bool
    {
        if ($secret === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$name, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($name === 't' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($name === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === [] || abs($now - $timestamp) > $toleranceSeconds) {
            return false;
        }

        $expected = self::compute($payload, $secret, $timestamp);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    private static function compute(string $payload, string $secret, int $timestamp): string
    {
        return hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
    }
}
