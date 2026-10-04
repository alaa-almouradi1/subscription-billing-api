<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            // Hash of the caller's API key: two clients may pick the same key.
            $table->char('scope', 64);
            $table->string('idempotency_key');
            // Hash of method + path + body: a reused key with a different
            // request is a client bug and must not replay the old response.
            $table->char('fingerprint', 64);
            $table->string('status', 16);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->dateTime('expires_at');
            $table->timestamps();

            $table->unique(['scope', 'idempotency_key']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
