<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Provider-specific extras for oauth_tokens. Pinterest needs to remember which
 * board new pins go to (board_id + a human-readable board_name); future
 * providers can stash their own bits here without a schema change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oauth_tokens', function (Blueprint $table) {
            $table->json('meta')->nullable()->after('account_name');
        });
    }

    public function down(): void
    {
        Schema::table('oauth_tokens', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
