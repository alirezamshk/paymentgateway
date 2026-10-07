<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Stable machine code that maps to an adapter in config/gateways.php (e.g. "zarinpal").
            $table->string('code', 50)->unique();
            $table->string('status', 20)->default('active')->index();
            // Non-secret, provider-wide settings (e.g. sandbox mode, endpoint overrides).
            $table->json('config')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_providers');
    }
};
