<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lightweight per-user log of posts already shown in the feed.
        // Older than feed.impression_ttl_days are pruned by FeedRanker.
        Schema::create('feed_impressions', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->timestamp('seen_at')->useCurrent();
            $table->unsignedSmallInteger('shown_count')->default(1);

            $table->primary(['user_id', 'post_id']);
            $table->index(['user_id', 'seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_impressions');
    }
};
