<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Widen posts.type for the new "tv" post type. Raw ALTER, matching the
        // 'book' widening — we don't carry doctrine/dbal just for enums.
        DB::statement("ALTER TABLE posts MODIFY COLUMN type ENUM('status','image','video','article','book','tv') NOT NULL");

        Schema::create('tv_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            // Either a storage path (uploaded logo) or an absolute URL.
            $table->string('logo', 1024)->nullable();
            $table->longText('description')->nullable();
            // The upstream HLS manifest. NEVER rendered to the page — the
            // player is handed a signed proxy URL instead (TvStreamController).
            $table->text('stream_url');
            // Optional upstream request headers some CDNs demand for playback.
            $table->string('referer', 1024)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'views']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tv_channels');
        DB::statement("ALTER TABLE posts MODIFY COLUMN type ENUM('status','image','video','article','book') NOT NULL");
    }
};
