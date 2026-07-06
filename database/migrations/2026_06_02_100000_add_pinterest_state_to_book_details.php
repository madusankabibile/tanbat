<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-book Pinterest cross-post state, mirroring the Reddit columns added in
 * 2026_05_25_130000. The heartbeat filters on pinterest_pin_id + attempts to
 * find the next book to pin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_details', function (Blueprint $table) {
            // Pinterest pin id — null until we successfully create the pin.
            $table->string('pinterest_pin_id', 64)->nullable()->after('reddit_last_error');
            $table->timestamp('pinterest_posted_at')->nullable()->after('pinterest_pin_id');
            $table->unsignedTinyInteger('pinterest_attempts')->default(0)->after('pinterest_posted_at');
            $table->string('pinterest_last_error', 500)->nullable()->after('pinterest_attempts');

            $table->index(['pinterest_pin_id', 'pinterest_attempts']);
        });
    }

    public function down(): void
    {
        Schema::table('book_details', function (Blueprint $table) {
            $table->dropIndex(['pinterest_pin_id', 'pinterest_attempts']);
            $table->dropColumn([
                'pinterest_pin_id', 'pinterest_posted_at',
                'pinterest_attempts', 'pinterest_last_error',
            ]);
        });
    }
};
