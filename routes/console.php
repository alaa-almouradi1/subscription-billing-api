<?php

use Illuminate\Support\Facades\Schedule;

// Renewal is idempotent, so running it often is safe; withoutOverlapping()
// only avoids wasted work when a run takes longer than a minute.
Schedule::command('billing:renew')->everyMinute()->withoutOverlapping();

// Deletes expired idempotency keys (see App\Models\IdempotencyKey::prunable).
Schedule::command('model:prune')->daily();
