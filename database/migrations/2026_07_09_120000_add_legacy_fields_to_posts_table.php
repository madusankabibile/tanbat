<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a native `posts` row represent a migrated old-Sngine blog article so it
 * plugs into the normal like/comment/share/profile stack. `legacy_post_id` is
 * the original Sngine posts.post_id (the /blogs/{id}/ segment); `is_legacy`
 * flags the row so the UI can badge it "Legacy" and build /blogs/{id}/{slug}
 * permalinks instead of /articles/{slug}. Populated by `legacy:sync-posts`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->boolean('is_legacy')->default(false)->index()->after('type');
            $table->unsignedInteger('legacy_post_id')->nullable()->unique()->after('is_legacy');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropUnique(['legacy_post_id']);
            $table->dropIndex(['is_legacy']);
            $table->dropColumn(['is_legacy', 'legacy_post_id']);
        });
    }
};
