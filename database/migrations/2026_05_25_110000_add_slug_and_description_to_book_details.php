<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_details', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('md5');
            $table->longText('description')->nullable()->after('size');
        });

        // Backfill slugs for any rows that already exist (the controller will
        // emit them going forward, but a pretty URL needs to work for old rows
        // too).
        $rows = DB::table('book_details')->whereNull('slug')->get(['id', 'title', 'md5']);
        foreach ($rows as $r) {
            $base = Str::slug($r->title) ?: 'book';
            $slug = Str::limit($base, 60, '');
            $candidate = $slug . '-' . substr($r->md5, 0, 8);
            DB::table('book_details')->where('id', $r->id)->update(['slug' => $candidate]);
        }

        Schema::table('book_details', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('book_details', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'description']);
        });
    }
};
