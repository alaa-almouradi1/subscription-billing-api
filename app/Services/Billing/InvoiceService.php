<?php

namespace App\Services\Billing;

use App\Domain\Billing\InvoiceStatus;
use App\Models\Invoice;
use App\Outbox\BillingEvents;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    /** Retries on deadlocks and lock-wait timeouts (the closure only touches the database). */
    private const TRANSACTION_ATTEMPTS = 3;

    public function __construct(private readonly BillingEvents $events) {}

    public function void(Invoice $invoice): Invoice
    {
        return $this->transition($invoice, InvoiceStatus::Void);
    }

    /**
     * Write the invoice off after collection attempts are exhausted.
     * It stays payable, so a late payment can still settle it.
     */
    public function markUncollectible(Invoice $invoice): Invoice
    {
        return $this->transition($invoice, InvoiceStatus::Uncollectible);
    }

    private function transition(Invoice $invoice, InvoiceStatus $next): Invoice
    {
        return DB::transaction(function () use ($invoice, $next) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            $invoice->transitionTo($next);

            if ($next === InvoiceStatus::Void) {
                $invoice->voided_at = now();
            }

            $invoice->save();

            match ($next) {
                InvoiceStatus::Void => $this->events->invoiceVoided($invoice),
                InvoiceStatus::Uncollectible => $this->events->invoiceMarkedUncollectible($invoice),
                default => null,
            };

            return $invoice;
        }, self::TRANSACTION_ATTEMPTS);
    }
}
