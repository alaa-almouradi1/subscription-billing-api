<?php

namespace App\Services\Billing;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Hands out gapless, per-year invoice numbers: INV-2026-000001, ...
 *
 * Must run inside the transaction that creates the invoice: the counter row
 * stays locked until commit, and a rollback also rolls the counter back, so
 * no number is ever skipped or used twice.
 */
class InvoiceNumberGenerator
{
    public function next(): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Invoice numbers must be generated inside a database transaction.');
        }

        $prefix = 'INV-'.now()->format('Y');

        DB::table('invoice_sequences')->insertOrIgnore(['prefix' => $prefix, 'last_value' => 0]);

        $last = (int) DB::table('invoice_sequences')
            ->where('prefix', $prefix)
            ->lockForUpdate()
            ->value('last_value');

        DB::table('invoice_sequences')
            ->where('prefix', $prefix)
            ->update(['last_value' => $last + 1]);

        return sprintf('%s-%06d', $prefix, $last + 1);
    }
}
