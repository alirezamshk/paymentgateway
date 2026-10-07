<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('gateway_providers')->restrictOnDelete();
            $table->string('name');
            // Non-secret identifiers, kept in plaintext for search/display.
            $table->string('merchant_identifier')->nullable();
            $table->string('terminal_identifier')->nullable();
            $table->string('username')->nullable();
            // Secrets - all encrypted at rest via Eloquent encrypted casts.
            $table->text('encrypted_password')->nullable();
            $table->text('encrypted_api_key')->nullable();
            $table->text('encrypted_config')->nullable();
            $table->string('status', 20)->default('active');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['client_id', 'is_default']);
            $table->index('provider_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
