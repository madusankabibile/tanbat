<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Covering indexes for the admin statistics leaderboards.
     *
     * `posts` rows are wide (legacy article bodies), so grouping by user_id via
     * the plain posts_user_id_foreign index meant a row lookup per post just to
     * read likes_count — 2.5s for ~8k posts. Both indexes below let the two hot
     * aggregates be answered from the index alone.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // SUM(likes_count) GROUP BY user_id  — "most liked" leaderboard.
            $table->index(['user_id', 'likes_count'], 'posts_user_likes_index');
            // EXISTS (... WHERE user_id = ? AND created_at >= ?) — "active members".
            $table->index(['user_id', 'created_at'], 'posts_user_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_user_likes_index');
            $table->dropIndex('posts_user_created_index');
        });
    }
};
