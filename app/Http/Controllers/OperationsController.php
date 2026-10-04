<?php

namespace App\Http\Controllers;

use App\Domain\Billing\InvoiceStatus;
use App\Models\Invoice;
use App\Models\OutboxMessage;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Endpoints for the platform rather than for API clients.
 *
 *  /up            liveness  (built into Laravel): the process is running
 *  /health/ready  readiness: dependencies are reachable, events are flowing
 *  /metrics       Prometheus text format
 */
class OperationsController extends Controller
{
    /** Unpublished events older than this mean the relay is stuck. */
    private const MAX_OUTBOX_LAG_SECONDS = 300;

    private const AGGREGATE_TTL_SECONDS = 60;

    public function ready(): JsonResponse
    {
        $checks = [];

        try {
            DB::select('select 1');
            $checks['database'] = 'ok';
        } catch (Throwable) {
            $checks['database'] = 'unreachable';
        }

        if ($checks['database'] === 'ok') {
            $lag = $this->outboxLagSeconds();
            $checks['outbox'] = $lag > self::MAX_OUTBOX_LAG_SECONDS ? "lagging ({$lag}s)" : 'ok';
        }

        $healthy = ! array_diff($checks, ['ok']);

        return response()->json(['status' => $healthy ? 'ok' : 'degraded', 'checks' => $checks], $healthy ? 200 : 503);
    }

    public function metrics(): Response
    {
        $lines = [
            '# HELP billing_outbox_backlog Domain events waiting to be published.',
            '# TYPE billing_outbox_backlog gauge',
            'billing_outbox_backlog '.OutboxMessage::query()->whereNull('published_at')->count(),
            '# HELP billing_outbox_lag_seconds Age of the oldest unpublished event.',
            '# TYPE billing_outbox_lag_seconds gauge',
            'billing_outbox_lag_seconds '.$this->outboxLagSeconds(),
            '# HELP billing_subscriptions Subscriptions by status.',
            '# TYPE billing_subscriptions gauge',
        ];

        // Aggregations over whole tables are cached briefly, so frequent
        // scrapes (and several Prometheus replicas) cost one query per minute.
        $aggregates = Cache::remember('billing:metrics:aggregates', self::AGGREGATE_TTL_SECONDS, fn () => [
            'subscriptions' => Subscription::query()->toBase()
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status')
                ->all(),
            'open_invoices' => Invoice::query()->toBase()
                ->where('status', InvoiceStatus::Open->value)
                ->select('currency', DB::raw('sum(amount_due) as total'))
                ->groupBy('currency')
                ->pluck('total', 'currency')
                ->all(),
        ]);

        foreach ($aggregates['subscriptions'] as $status => $total) {
            $lines[] = "billing_subscriptions{status=\"{$status}\"} {$total}";
        }

        $lines[] = '# HELP billing_open_invoices_amount Outstanding amount on open invoices, in minor units.';
        $lines[] = '# TYPE billing_open_invoices_amount gauge';

        foreach ($aggregates['open_invoices'] as $currency => $total) {
            $lines[] = "billing_open_invoices_amount{currency=\"{$currency}\"} {$total}";
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; version=0.0.4']);
    }

    private function outboxLagSeconds(): int
    {
        $oldest = OutboxMessage::query()->whereNull('published_at')->min('occurred_at');

        return $oldest === null ? 0 : max(0, now()->getTimestamp() - strtotime((string) $oldest.' UTC'));
    }
}
