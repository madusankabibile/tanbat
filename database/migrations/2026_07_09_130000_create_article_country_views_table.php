<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-country view counters for article posts.
 *
 * Deliberately a SEPARATE table from `posts` (whose `views_count` stays the
 * global total). One row per (article, visitor-country): each time an article
 * is opened we resolve the visitor's country from their IP and bump the matching
 * counter. The /blog "For you" feed then surfaces articles that readers from the
 * *visitor's own* country actually read — independent of who authored them. So a
 * post read heavily by Japanese visitors gets recommended to more Japanese
 * visitors, even if its author is elsewhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_country_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->char('country_code', 2);           // ISO-3166 alpha-2, uppercase
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamps();

            // One counter per article per country.
            $table->unique(['post_id', 'country_code']);
            // Ranking query filters/sorts by country then joins on post_id.
            $table->index(['country_code', 'views']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_country_views');
    }
};
