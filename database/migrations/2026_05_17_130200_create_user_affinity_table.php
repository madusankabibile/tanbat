<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_affinity', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // dimension: 'category' | 'tag' | 'author' | 'type' | 'language' | 'country'
            $table->string('dimension', 16);
            // For 'type' / 'language' / 'country' dimension we don't have a FK id —
            // store the string value here. For category/tag/author we store the id.
            // We use a single varchar to keep schema small.
            $table->string('dimension_value', 64);
            $table->float('score')->default(0);              // rolling affinity, can be negative
            $table->unsignedInteger('events')->default(0);   // count of contributing events
            $table->timestamp('updated_at')->useCurrent();

            $table->primary(['user_id', 'dimension', 'dimension_value']);
            $table->index(['user_id', 'dimension', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_affinity');
    }
};
