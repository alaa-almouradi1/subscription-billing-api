<?php

namespace App\Services\Payments;

use App\Domain\BillingException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * Charges newly issued invoices automatically (subscription start, renewal,
 * upgrade). Failures are normal business outcomes here: they are recorded
 * on the payment and picked up by dunning, never thrown at the caller.
 */
class AutoCollector
{
    public function __construct(private readonly PaymentService $payments) {}

    public function collect(?Invoice $invoice): ?Payment
    {
        if ($invoice === null || ! $invoice->status->isPayable() || $invoice->amount_due === 0) {
            return null;
        }

        try {
            $hasPaymentMethod = Customer::query()
                ->whereKey($invoice->customer_id)
                ->whereNotNull('default_payment_method')
                ->exists();

            return $hasPaymentMethod
                ? $this->payments->pay($invoice)
                : $this->payments->recordMissingPaymentMethod($invoice);
        } catch (BillingException $e) {
            Log::info('Automatic collection did not complete', [
                'invoice_id' => $invoice->id,
                'reason' => $e->errorCode(),
            ]);

            return null;
        }
    }
}
