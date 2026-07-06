<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_details', function (Blueprint $table) {
            // Reddit fullname (e.g. "t3_abc123") — null until we successfully post.
            $table->string('reddit_post_id', 32)->nullable()->after('description');
            $table->timestamp('reddit_posted_at')->nullable()->after('reddit_post_id');
            $table->unsignedTinyInteger('reddit_attempts')->default(0)->after('reddit_posted_at');
            $table->string('reddit_last_error', 500)->nullable()->after('reddit_attempts');

            // The heartbeat scan filters on this pair, so an index speeds it up
            // as the library grows.
            $table->index(['reddit_post_id', 'reddit_attempts']);
        });
    }

    public function down(): void
    {
        Schema::table('book_details', function (Blueprint $table) {
            $table->dropIndex(['reddit_post_id', 'reddit_attempts']);
            $table->dropColumn(['reddit_post_id', 'reddit_posted_at', 'reddit_attempts', 'reddit_last_error']);
        });
    }
};
