<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['status', 'image', 'video', 'article']);
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            // Article-specific
            $table->string('title')->nullable();
            $table->string('slug')->nullable()->unique();
            $table->string('featured_image')->nullable();
            $table->string('short_description', 800)->nullable();
            $table->longText('body')->nullable();

            // Status-specific
            $table->text('status_text')->nullable();
            $table->string('bg_color', 32)->nullable();
            $table->string('font_color', 32)->nullable();

            // Image/Video shared
            $table->text('description')->nullable();
            $table->boolean('is_adult')->default(false);
            $table->string('thumbnail')->nullable();   // for video

            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->unsignedInteger('views_count')->default(0);

            $table->timestamps();
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
