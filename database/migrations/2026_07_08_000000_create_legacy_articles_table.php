<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy (Sngine) blog articles imported from the old tanbat.com site.
 *
 * This is a self-contained table that has NOTHING to do with the new
 * `posts` (type=article) blog. It exists only to keep the old
 * /blogs/{post_id}/{slug} URLs alive for SEO. Keyed by the original
 * Sngine `posts.post_id` (the /2714/ in the URL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_articles', function (Blueprint $table) {
            $table->id();

            // The /2714/ segment of the old URL == old Sngine posts.post_id
            $table->unsignedInteger('old_post_id')->unique();
            $table->unsignedInteger('old_article_id')->nullable();

            // Author, resolved to a migrated user account when a username/email
            // match exists; author_name/username are always denormalised so the
            // byline renders even when no account matched.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name')->nullable();
            $table->string('author_username')->nullable();

            $table->string('title', 512);
            $table->string('slug', 512)->index();

            // Old cover path, e.g. "photos/2023/11/tanbat_xxx.jpg". Resolved to a
            // full URL at render time via config('legacy.uploads_base').
            $table->string('cover', 512)->nullable();

            // HTML body (entity-decoded on import so it renders as markup).
            $table->longText('body')->nullable();

            $table->unsignedInteger('old_category_id')->nullable();
            $table->string('category_name')->nullable();
            $table->string('category_slug')->nullable();

            $table->text('tags')->nullable();

            $table->unsignedInteger('views')->default(0);

            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_articles');
    }
};
