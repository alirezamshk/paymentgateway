<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Payer details sent by the client site (optional), normalized for search.
            $table->string('customer_mobile', 20)->nullable()->after('description');
            $table->string('customer_username', 100)->nullable()->after('customer_mobile');
            $table->string('customer_name', 150)->nullable()->after('customer_username');
            // Last 4 digits of the masked PAN, for search.
            $table->char('card_last4', 4)->nullable()->after('card_mask');

            $table->index('customer_mobile');
            $table->index('customer_username');
            $table->index('card_last4');
        });

        // Backfill last 4 digits for payments that already have a masked card.
        DB::table('payments')->whereNotNull('card_mask')->orderBy('id')->each(function ($row) {
            $digits = preg_replace('/\D/', '', (string) $row->card_mask);

            if (strlen($digits) >= 4) {
                DB::table('payments')->where('id', $row->id)->update(['card_last4' => substr($digits, -4)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['customer_mobile']);
            $table->dropIndex(['customer_username']);
            $table->dropIndex(['card_last4']);
            $table->dropColumn(['customer_mobile', 'customer_username', 'customer_name', 'card_last4']);
        });
    }
};
