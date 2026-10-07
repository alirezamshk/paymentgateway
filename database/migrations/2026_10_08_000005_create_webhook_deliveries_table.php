<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            // Sent as X-TK-Delivery-Id so receivers can de-duplicate.
            $table->string('public_id', 40)->unique();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('event', 50);
            // Idempotency for event creation: one delivery per payment state change.
            $table->string('dedupe_key', 150)->unique();
            $table->string('endpoint', 2048);
            $table->json('payload');
            $table->unsignedInteger('attempt')->default(0);
            // pending | processing | delivered | failed
            $table->string('status', 20);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('response_body')->nullable();
            $table->string('last_error', 1000)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index('payment_id');
            $table->index(['status', 'next_retry_at']);
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
