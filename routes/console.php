<?php

use Illuminate\Support\Facades\Schedule;

// Renewal is idempotent, so running it often is safe. onOneServer() lets
// several scheduler instances run without queueing the same work twice
// (needs a shared cache store such as Redis); the jobs run on queue workers.
Schedule::command('billing:renew')->everyMinute()->withoutOverlapping()->onOneServer();

// Deletes expired idempotency keys, old webhook events and published outbox
// messages (see the prunable() methods and config/billing.php "retention").
Schedule::command('model:prune')->daily()->onOneServer();
