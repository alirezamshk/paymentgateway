<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // Public identifier (pay_<ulid>). Sequential ids are never exposed.
            $table->string('public_id', 40)->unique();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('merchant_id')->constrained('merchants')->restrictOnDelete();
            $table->foreignId('provider_id')->constrained('gateway_providers')->restrictOnDelete();
            $table->string('order_id', 100);
            $table->string('idempotency_key', 255)->nullable();
            $table->char('request_hash', 64)->nullable();
            // Integer amount in the unit of `currency` (IRR = Rial, IRT = Toman). Never float.
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->string('description', 500)->nullable();
            $table->string('status', 30)->index();
            $table->string('payment_url', 2048)->nullable();
            $table->string('authority', 255)->nullable();
            $table->string('token', 512)->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->string('trace_number', 100)->nullable();
            // Masked PAN only (e.g. 603799******1234). Full PAN / CVV are never stored.
            $table->string('card_mask', 32)->nullable();
            $table->string('callback_url', 2048)->nullable();
            $table->string('return_url', 2048)->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('attempts_count')->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'order_id']);
            $table->unique(['client_id', 'idempotency_key']);
            $table->index('order_id');
            $table->index('merchant_id');
            $table->index('provider_id');
            $table->index(['status', 'updated_at']);
            $table->index('reference_number');
            $table->index('created_at');
        });

        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('merchant_id')->constrained('merchants')->restrictOnDelete();
            $table->foreignId('provider_id')->constrained('gateway_providers')->restrictOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('status', 30);
            // Numeric invoice id sent to PSPs that require one (unique per provider).
            $table->unsignedBigInteger('psp_invoice_id');
            $table->string('authority', 255)->nullable();
            $table->string('token', 512)->nullable();
            // How the customer is sent to the PSP: {method, url, fields}.
            $table->json('redirect_payload')->nullable();
            // Masked copies of PSP traffic.
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->json('callback_payload')->nullable();
            $table->json('verify_payload')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->string('error_message', 1000)->nullable();
            $table->timestamps();

            $table->unique(['payment_id', 'attempt_number']);
            $table->unique(['provider_id', 'psp_invoice_id']);
            $table->index('payment_id');
            $table->index('authority');
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('payment_attempt_id')->nullable()->constrained('payment_attempts')->nullOnDelete();
            $table->string('event', 100);
            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30)->nullable();
            // api | callback | system | admin | scheduler | webhook
            $table->string('source', 30);
            $table->string('request_id', 64)->nullable();
            $table->json('metadata')->nullable();
            // Append-only: no updated_at.
            $table->timestamp('created_at')->useCurrent();

            $table->index('payment_id');
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('payments');
    }
};
