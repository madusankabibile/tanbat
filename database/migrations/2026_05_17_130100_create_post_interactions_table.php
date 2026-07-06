<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            // impression = card scrolled into view; click = card opened;
            // dwell = time spent on opened post; like/unlike/comment/share = explicit signal.
            $table->enum('event', ['impression', 'click', 'dwell', 'like', 'unlike', 'comment', 'share']);
            $table->float('weight')->default(1);          // signal strength used by AffinityCalculator
            $table->unsignedInteger('dwell_ms')->nullable(); // populated for dwell events
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['post_id', 'event']);
            $table->index(['user_id', 'event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_interactions');
    }
};
