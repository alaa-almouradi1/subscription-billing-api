<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_requests_without_an_api_key_are_rejected(): void
    {
        $this->withoutHeader('X-Api-Key')
            ->getJson('/api/v1/plans')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_requests_with_a_wrong_api_key_are_rejected(): void
    {
        $this->withHeader('X-Api-Key', 'nope')
            ->getJson('/api/v1/plans')
            ->assertUnauthorized();
    }

    public function test_any_configured_key_is_accepted_to_allow_rotation(): void
    {
        config(['billing.api_keys' => ['old-key', 'new-key']]);

        $this->withHeader('X-Api-Key', 'old-key')->getJson('/api/v1/plans')->assertOk();
        $this->withHeader('X-Api-Key', 'new-key')->getJson('/api/v1/plans')->assertOk();
    }

    public function test_the_root_endpoint_describes_the_service(): void
    {
        $this->get('/')->assertOk()->assertJsonStructure(['service', 'api', 'health']);
    }
}
