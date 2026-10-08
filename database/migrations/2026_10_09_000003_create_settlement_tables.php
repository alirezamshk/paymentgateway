<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Manual bank transfers from Tech-Kala to a client.
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->unsignedBigInteger('amount_irr');
            $table->string('iban', 34)->nullable();
            $table->string('account_holder', 150)->nullable();
            $table->string('bank_reference', 100);
            $table->date('paid_on');
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'paid_on']);
        });

        // Append-only ledger. Balance owed to a client = SUM(amount_irr). All amounts in Rials.
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            // payment | commission | payout | adjustment
            $table->string('type', 20);
            // Signed: credits to the client are positive, debits negative.
            $table->bigInteger('amount_irr');
            $table->foreignId('payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->foreignId('payout_id')->nullable()->constrained('payouts')->restrictOnDelete();
            $table->string('description', 500)->nullable();
            $table->timestamp('available_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            // A payment is credited (and charged commission) at most once.
            $table->unique(['payment_id', 'type']);
            $table->index(['client_id', 'available_at']);
            $table->index(['client_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('payouts');
    }
};
