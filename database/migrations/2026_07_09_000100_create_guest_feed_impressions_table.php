<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-guest (IP-keyed) log of posts already shown in the anonymous feed.
        // The guest equivalent of feed_impressions — lets logged-out visitors get
        // fresh, rotating content on every refresh instead of the same static
        // ranking. Keyed by a SHA-256 of the visitor's IP (never the raw IP).
        // Rows older than feed.guest_impression_ttl_hours are pruned by FeedRanker.
        Schema::create('guest_feed_impressions', function (Blueprint $table) {
            $table->char('ip_hash', 64);
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->timestamp('seen_at')->useCurrent();
            $table->unsignedSmallInteger('shown_count')->default(1);

            $table->primary(['ip_hash', 'post_id']);
            $table->index(['ip_hash', 'seen_at']);
            $table->index('seen_at'); // for pruning
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_feed_impressions');
    }
};
