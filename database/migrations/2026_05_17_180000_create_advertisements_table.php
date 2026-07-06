<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertisements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->text('body')->nullable();
            $table->string('link_url', 500);
            $table->string('image')->nullable();
            $table->enum('placement', ['assistant', 'sidebar', 'feed', 'banner'])->default('assistant');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('weight')->default(1);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['placement', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertisements');
    }
};
