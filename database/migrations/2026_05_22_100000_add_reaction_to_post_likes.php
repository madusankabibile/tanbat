<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Turn the single "like" into a Facebook-style reaction. Each row in
     * post_likes already represents exactly one user's response to a post
     * (or a media item) thanks to the (post_id, media_id, user_id) unique
     * index, so we only need to record *which* reaction it is. Existing
     * likes become 'like' via the column default.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('post_likes', 'reaction')) {
            Schema::table('post_likes', function (Blueprint $table) {
                $table->string('reaction', 16)->default('like')->after('user_id');
                $table->index(['post_id', 'reaction']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('post_likes', function (Blueprint $table) {
            $table->dropIndex(['post_id', 'reaction']);
            $table->dropColumn('reaction');
        });
    }
};
