<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Optional commission: basis points (100 = 1%) plus a fixed amount in Rials, per payment.
            $table->unsignedSmallInteger('commission_bps')->default(0)->after('return_url');
            $table->unsignedBigInteger('commission_fixed_irr')->default(0)->after('commission_bps');
            // Hours after payment before the money counts as available for payout.
            $table->unsignedSmallInteger('settlement_delay_hours')->default(0)->after('commission_fixed_irr');
            $table->string('iban', 34)->nullable()->after('settlement_delay_hours');
            $table->string('account_holder', 150)->nullable()->after('iban');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['commission_bps', 'commission_fixed_irr', 'settlement_delay_hours', 'iban', 'account_holder']);
        });
    }
};
