<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Providers deliver webhooks "at least once": the same event can
        // arrive several times. The provider's event ID as primary key turns
        // the duplicate check into a single insert.
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('type', 64);
            $table->json('payload');
            $table->dateTime('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
