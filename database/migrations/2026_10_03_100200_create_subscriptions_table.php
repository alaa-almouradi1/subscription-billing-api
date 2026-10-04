<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 16);
            // Every period boundary is derived from this anchor (see BillingCalendar).
            $table->dateTime('billing_cycle_anchor');
            $table->unsignedInteger('periods_billed')->default(0);
            $table->dateTime('current_period_start');
            $table->dateTime('current_period_end');
            $table->dateTime('trial_ends_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->dateTime('canceled_at')->nullable();
            $table->timestamps();

            // The renewal job scans for "renewing subscriptions whose period ended".
            $table->index(['status', 'current_period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
