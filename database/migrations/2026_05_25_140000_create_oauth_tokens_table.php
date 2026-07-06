<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durable token store for third-party OAuth integrations (currently: Reddit).
 *
 * One row per provider. We only need a single set of tokens — these are
 * "system" credentials shared across the install, not per-user tokens — so
 * `provider` is the natural unique key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32)->unique();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->string('scope', 255)->nullable();
            $table->string('account_name')->nullable(); // human label, e.g. Reddit username
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_tokens');
    }
};
