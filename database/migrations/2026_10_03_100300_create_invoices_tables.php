<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Invoice numbers must be sequential without gaps in most EU
        // jurisdictions, so they come from a locked counter row rather than
        // an auto-increment (which skips values on rolled-back inserts).
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->string('prefix', 16)->primary();
            $table->unsignedBigInteger('last_value');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 32)->unique();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('subscription_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 16);
            $table->char('currency', 3);
            $table->bigInteger('subtotal');
            $table->unsignedBigInteger('credit_applied')->default(0);
            $table->unsignedBigInteger('amount_due');
            $table->dateTime('period_start')->nullable();
            $table->dateTime('period_end')->nullable();
            $table->dateTime('issued_at');
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->timestamps();

            // Safety net under the renewal job: a subscription can never be
            // invoiced twice for the same period, even if two workers race.
            // (Proration invoices have no period; NULLs do not collide.)
            $table->unique(['subscription_id', 'period_start']);
            $table->index(['customer_id', 'issued_at']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('description');
            $table->bigInteger('amount');
            $table->dateTime('period_start')->nullable();
            $table->dateTime('period_end')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_sequences');
    }
};
