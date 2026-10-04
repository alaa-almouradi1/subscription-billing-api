<?php

namespace App\Services\Billing;

use App\Domain\Billing\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
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

            return $invoice;
        });
    }
}
