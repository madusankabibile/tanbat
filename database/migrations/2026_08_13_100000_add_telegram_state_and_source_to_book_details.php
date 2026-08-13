<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-book Telegram cross-post state, mirroring the Reddit columns added in
 * 2026_05_25_130000 and the Pinterest ones in 2026_06_02_100000. The heartbeat
 * filters on telegram_message_id + attempts to find the next book to announce.
 *
 * Also adds source_url — the origin permalink for books ingested from an RSS
 * feed (sinhalaebooks.com), kept for attribution on the book page and so an
 * admin can trace any imported row back to where it came from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_details', function (Blueprint $table) {
            // Telegram message id within the target channel — null until we
            // successfully post. Stored as a string for symmetry with the other
            // two networks (Telegram's own ids are integers).
            $table->string('telegram_message_id', 64)->nullable()->after('pinterest_last_error');
            $table->timestamp('telegram_posted_at')->nullable()->after('telegram_message_id');
            $table->unsignedTinyInteger('telegram_attempts')->default(0)->after('telegram_posted_at');
            $table->string('telegram_last_error', 500)->nullable()->after('telegram_attempts');

            // Permalink of the feed item this book was imported from.
            $table->string('source_url', 1024)->nullable()->after('telegram_last_error');

            $table->index(['telegram_message_id', 'telegram_attempts']);
        });
    }

    public function down(): void
    {
        Schema::table('book_details', function (Blueprint $table) {
            $table->dropIndex(['telegram_message_id', 'telegram_attempts']);
            $table->dropColumn([
                'telegram_message_id', 'telegram_posted_at',
                'telegram_attempts', 'telegram_last_error',
                'source_url',
            ]);
        });
    }
};
