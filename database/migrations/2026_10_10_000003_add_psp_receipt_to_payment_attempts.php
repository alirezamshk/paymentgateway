<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The PSP's proof of payment (e.g. Sepehr's digital receipt), claimed by exactly one attempt.
 * The unique index stops one real payment's receipt from being replayed onto another payment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table) {
            $table->string('psp_receipt', 191)->nullable()->after('token');
            $table->unique(['provider_id', 'psp_receipt']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table) {
            $table->dropUnique(['provider_id', 'psp_receipt']);
            $table->dropColumn('psp_receipt');
        });
    }
};
