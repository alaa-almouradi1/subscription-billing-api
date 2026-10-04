<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pruning: DELETE ... WHERE received_at <= ? must not scan the table.
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->index('received_at');
        });

        // Reconciliation: payments stuck in "processing" since before X.
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['status', 'created_at']);
        });

        // Metrics: outstanding amount per currency on open invoices.
        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['status', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $table) => $table->dropIndex(['status', 'currency']));
        Schema::table('payments', fn (Blueprint $table) => $table->dropIndex(['status', 'created_at']));
        Schema::table('webhook_events', fn (Blueprint $table) => $table->dropIndex(['received_at']));
    }
};
