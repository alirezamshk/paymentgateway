<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->string('status', 20)->default('active')->index();
            $table->string('webhook_url', 2048)->nullable();
            // Encrypted at rest (APP_KEY). Needed in plaintext to sign outgoing webhooks.
            $table->text('webhook_secret')->nullable();
            $table->string('return_url', 2048)->nullable();
            $table->timestamps();
        });

        Schema::create('client_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            // Public identifier sent as X-Client-Id.
            $table->string('key_id', 64)->unique();
            // Encrypted at rest. HMAC needs the shared secret, so a one-way hash is not possible.
            $table->text('encrypted_secret');
            $table->string('status', 20)->default('active');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_credentials');
        Schema::dropIfExists('clients');
    }
};
