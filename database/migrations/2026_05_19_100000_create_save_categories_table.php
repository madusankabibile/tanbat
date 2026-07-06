<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('save_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('slug', 80);
            $table->timestamps();
            $table->unique(['user_id', 'slug']);
        });

        Schema::table('post_saves', function (Blueprint $table) {
            $table->foreignId('save_category_id')
                ->nullable()
                ->after('post_id')
                ->constrained('save_categories')
                ->nullOnDelete();
            $table->index(['user_id', 'save_category_id']);
        });
    }

    public function down(): void
    {
        Schema::table('post_saves', function (Blueprint $table) {
            $table->dropForeign(['save_category_id']);
            $table->dropIndex(['user_id', 'save_category_id']);
            $table->dropColumn('save_category_id');
        });
        Schema::dropIfExists('save_categories');
    }
};
