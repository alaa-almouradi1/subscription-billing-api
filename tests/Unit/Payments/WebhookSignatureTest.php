<?php

namespace Tests\Unit\Payments;

use App\Domain\Payments\WebhookSignature;
use PHPUnit\Framework\TestCase;

class WebhookSignatureTest extends TestCase
{
    private const SECRET = 'whsec_test';

    private const NOW = 1_767_225_600;

    private const PAYLOAD = '{"id":"evt_1","type":"payment.succeeded"}';

    public function test_a_valid_signature_is_accepted(): void
    {
        $header = WebhookSignature::header(self::PAYLOAD, self::SECRET, self::NOW);

        $this->assertTrue(WebhookSignature::verify(self::PAYLOAD, $header, self::SECRET, self::NOW));
    }

    public function test_a_tampered_payload_is_rejected(): void
    {
        $header = WebhookSignature::header(self::PAYLOAD, self::SECRET, self::NOW);

        $this->assertFalse(WebhookSignature::verify(str_replace('succeeded', 'failed', self::PAYLOAD), $header, self::SECRET, self::NOW));
    }

    public function test_the_wrong_secret_is_rejected(): void
    {
        $header = WebhookSignature::header(self::PAYLOAD, 'another-secret', self::NOW);

        $this->assertFalse(WebhookSignature::verify(self::PAYLOAD, $header, self::SECRET, self::NOW));
    }

    public function test_old_requests_are_rejected_to_prevent_replays(): void
    {
        $header = WebhookSignature::header(self::PAYLOAD, self::SECRET, self::NOW - 301);

        $this->assertFalse(WebhookSignature::verify(self::PAYLOAD, $header, self::SECRET, self::NOW));
    }

    public function test_any_matching_signature_is_accepted_during_secret_rotation(): void
    {
        $valid = WebhookSignature::header(self::PAYLOAD, self::SECRET, self::NOW);
        $header = 't='.self::NOW.',v1=deadbeef,'.explode(',', $valid)[1];

        $this->assertTrue(WebhookSignature::verify(self::PAYLOAD, $header, self::SECRET, self::NOW));
    }

    public function test_malformed_headers_are_rejected(): void
    {
        foreach (['', 'garbage', 'v1=abc', 't=notanumber,v1=abc', 't='.self::NOW] as $header) {
            $this->assertFalse(WebhookSignature::verify(self::PAYLOAD, $header, self::SECRET, self::NOW), $header);
        }
    }

    public function test_an_empty_secret_never_verifies(): void
    {
        $header = WebhookSignature::header(self::PAYLOAD, '', self::NOW);

        $this->assertFalse(WebhookSignature::verify(self::PAYLOAD, $header, '', self::NOW));
    }
}
