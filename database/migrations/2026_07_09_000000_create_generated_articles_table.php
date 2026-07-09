<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI-generated blog articles that back the old WoWonder-style
 * /read-blog/{id}_{slug}.html URLs.
 *
 * These pages were NOT migrated (the source content no longer exists), so the
 * first time such a URL is visited we synthesise an article from its title via
 * the Groq LLM, persist it here, and serve the stored copy on every later hit
 * — nothing is ever regenerated. Keyed by the numeric id in the URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_articles', function (Blueprint $table) {
            $table->id();

            // The {id} segment of /read-blog/{id}_{slug}.html — the canonical key.
            $table->unsignedInteger('old_id')->unique();

            // The slug portion of the URL (no id, no ".html"). Kept for canonical
            // link rebuilding; the id alone resolves the article.
            $table->string('slug', 512)->index();

            $table->string('title', 512);
            $table->string('excerpt', 512)->nullable();

            // AI-produced HTML body (sanitised on save).
            $table->longText('body')->nullable();

            $table->string('category')->nullable();
            $table->text('tags')->nullable();

            $table->unsignedInteger('views')->default(0);

            // Which model produced the body (audit / future re-generation).
            $table->string('model')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_articles');
    }
};
