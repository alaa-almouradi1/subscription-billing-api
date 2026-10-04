<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->string('status', 16);
            $table->string('payment_method');
            $table->string('psp_reference')->nullable()->unique();
            $table->string('failure_code', 64)->nullable();
            $table->string('failure_message')->nullable();
            $table->dateTime('settled_at')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
