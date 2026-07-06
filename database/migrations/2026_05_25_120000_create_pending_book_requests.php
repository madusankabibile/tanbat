<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pending book-creation queue. The wizard's "confirm" call inserts a row here
 * with due_at = now + 2 minutes; the post-creation task (driven by /tasks/run
 * and also kickable from the wizard's polling endpoint) processes rows whose
 * due_at has passed.
 *
 * Survives browser closes — that's the whole point. A user who clicks "Yes,
 * that's it" and walks away still gets their notification later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_book_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('md5', 32);
            $table->string('query');
            $table->json('payload'); // cached scrape row (title, author, cover, url, etc.)
            $table->timestamp('due_at');
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->timestamps();

            $table->index(['processed_at', 'due_at']);
            $table->index(['user_id', 'md5']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_book_requests');
    }
};
