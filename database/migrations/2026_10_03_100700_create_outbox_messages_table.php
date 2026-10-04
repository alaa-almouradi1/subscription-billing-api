<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table) {
            // Auto-increment gives the relay a strict commit-order sequence.
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('event_type', 64);
            // Kafka message key: events with the same key stay in order.
            $table->string('partition_key');
            $table->json('payload');
            $table->dateTime('occurred_at', precision: 6);
            $table->dateTime('published_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();

            $table->index(['published_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
