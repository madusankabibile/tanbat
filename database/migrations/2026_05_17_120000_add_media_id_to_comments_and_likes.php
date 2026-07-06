<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('comments', 'media_id')) {
            Schema::table('comments', function (Blueprint $table) {
                $table->foreignId('media_id')
                    ->nullable()
                    ->after('post_id')
                    ->constrained('post_media')
                    ->nullOnDelete();
                $table->index(['post_id', 'media_id', 'created_at']);
            });
        }

        // post_likes: replace (post_id, user_id) unique with (post_id, media_id, user_id).
        // MySQL won't drop the old unique while it backs the post_id/user_id FKs, so
        // we drop the FKs first, swap indexes, then re-add the FKs.
        Schema::table('post_likes', function (Blueprint $table) {
            $table->dropForeign(['post_id']);
            $table->dropForeign(['user_id']);
            $table->dropUnique(['post_id', 'user_id']);
        });

        Schema::table('post_likes', function (Blueprint $table) {
            if (!Schema::hasColumn('post_likes', 'media_id')) {
                $table->unsignedBigInteger('media_id')->nullable()->after('post_id');
            }
            $table->foreign('media_id')->references('id')->on('post_media')->cascadeOnDelete();
            $table->unique(['post_id', 'media_id', 'user_id'], 'post_likes_post_media_user_unique');
            $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('post_likes', function (Blueprint $table) {
            $table->dropForeign(['post_id']);
            $table->dropForeign(['user_id']);
            $table->dropForeign(['media_id']);
            $table->dropUnique('post_likes_post_media_user_unique');
            $table->dropColumn('media_id');
        });

        Schema::table('post_likes', function (Blueprint $table) {
            $table->unique(['post_id', 'user_id']);
            $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex(['post_id', 'media_id', 'created_at']);
            $table->dropConstrainedForeignId('media_id');
        });
    }
};
