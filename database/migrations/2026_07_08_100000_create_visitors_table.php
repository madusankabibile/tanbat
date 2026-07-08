<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per distinct visitor (token = hash of IP + user agent).
        // updateOrCreate on each page view keeps the row's latest page /
        // referrer / country and bumps updated_at, so "last 8 visitors" is
        // just `order by updated_at desc limit 8`. Powers partials/stat-counter.
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_token', 40)->unique();
            $table->string('ip_address', 45)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('country_name', 80)->nullable();
            $table->string('page', 255)->nullable();      // path of the last page viewed
            $table->string('referrer', 255)->nullable();  // Referer header (raw)
            $table->string('user_agent', 255)->nullable();
            $table->unsignedInteger('hits')->default(1);
            $table->timestamps();

            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};
