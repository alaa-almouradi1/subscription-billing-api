<?php

namespace App\Console\Commands;

use App\Auth\ApiClientRegistry;
use Illuminate\Console\Command;

class CreateApiClient extends Command
{
    protected $signature = 'billing:client
        {name : Name of the calling service, e.g. dunning-service}
        {--scope=* : Scopes to grant (repeatable), e.g. --scope=billing.read --scope=payments.write}';

    protected $description = 'Generate an API key and the configuration entry for BILLING_API_CLIENTS';

    public function handle(): int
    {
        $scopes = $this->option('scope') ?: ['billing.read'];
        $key = ApiClientRegistry::generateKey();

        $this->line('API key (shown once, give it to the client through your secret store):');
        $this->line("  {$key}");
        $this->newLine();
        $this->line('Add this entry to the BILLING_API_CLIENTS JSON list:');
        $this->line('  '.json_encode([
            'name' => $this->argument('name'),
            'key_sha256' => hash('sha256', $key),
            'scopes' => array_values($scopes),
        ], JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
