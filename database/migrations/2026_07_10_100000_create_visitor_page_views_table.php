<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Daily page-view aggregate: one row per (visitor, path, day), bumped by
        // RecordVisitor on every view. The `visitors` table only ever keeps a
        // visitor's *last* page, so it cannot answer "views per day" or "top
        // pages" — this table can, at one small upsert per view rather than one
        // row per view.
        //
        // Key size: (40 + 255) * 4 bytes + 3 (DATE) = 1183, under InnoDB's
        // 3072-byte DYNAMIC row-format limit.
        Schema::create('visitor_page_views', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_token', 40);   // sha1(ip|user agent), matches visitors.visitor_token
            $table->string('path', 255);           // request path, leading slash
            $table->date('day');                   // bucket; views are only ever aggregated per day
            $table->unsignedInteger('hits')->default(1);
            $table->timestamps();

            $table->unique(['visitor_token', 'path', 'day'], 'vpv_token_path_day_unique');
            $table->index('day');                       // daily series + pruning
            $table->index(['day', 'path'], 'vpv_day_path_index'); // top pages within a window
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_page_views');
    }
};
