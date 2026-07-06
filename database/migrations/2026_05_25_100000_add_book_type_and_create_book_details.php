<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend posts.type ENUM to include 'book'. Raw ALTER so we don't need
        // doctrine/dbal just for an enum widening.
        DB::statement("ALTER TABLE posts MODIFY COLUMN type ENUM('status','image','video','article','book') NOT NULL");

        Schema::create('book_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->unique()->constrained()->cascadeOnDelete();
            // Anna's Archive md5 is the natural dedup key — two users requesting
            // the same book should land on one post, not two.
            $table->string('md5', 32)->unique();
            $table->string('title');
            $table->string('author')->nullable();
            $table->string('publisher')->nullable();
            $table->string('year', 8)->nullable();
            $table->string('language', 64)->nullable();
            $table->string('extension', 16)->nullable();
            $table->string('size', 32)->nullable();
            $table->string('cover_url', 1024)->nullable();
            $table->string('download_url', 1024)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_details');
        DB::statement("ALTER TABLE posts MODIFY COLUMN type ENUM('status','image','video','article') NOT NULL");
    }
};
