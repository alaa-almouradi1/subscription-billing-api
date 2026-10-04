<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            // Token issued by the payment provider; we never store card data.
            $table->string('default_payment_method')->nullable();
            // Credit from downgrades, applied to the next invoice (minor units).
            $table->unsignedBigInteger('credit_balance')->default(0);
            $table->char('credit_currency', 3)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
