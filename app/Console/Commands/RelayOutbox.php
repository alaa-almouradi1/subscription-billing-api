<?php

namespace App\Console\Commands;

use App\Outbox\OutboxRelay;
use Illuminate\Console\Command;

class RelayOutbox extends Command
{
    protected $signature = 'outbox:relay
        {--once : Publish one batch and exit}
        {--batch=100 : Messages per batch}
        {--sleep=1000 : Milliseconds to wait when there is nothing to publish}';

    protected $description = 'Publish recorded domain events to the message broker';

    private bool $running = true;

    public function handle(OutboxRelay $relay): int
    {
        // Finish the current batch on SIGTERM/SIGINT (e.g. a Kubernetes pod
        // shutdown) instead of dying halfway through it. Needs ext-pcntl,
        // which is not available on Windows.
        if (extension_loaded('pcntl')) {
            $this->trap([SIGTERM, SIGINT], fn () => $this->running = false);
        }

        do {
            $published = $relay->relayBatch((int) $this->option('batch'));

            if ($published > 0) {
                $this->line("Published {$published} event(s), backlog {$relay->backlog()}.");
            } elseif (! $this->option('once')) {
                usleep((int) $this->option('sleep') * 1000);
            }
        } while ($this->running && ! $this->option('once'));

        return self::SUCCESS;
    }
}
