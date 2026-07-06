<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('embed_provider', 32)->nullable()->after('thumbnail');
            $table->string('embed_id', 128)->nullable()->after('embed_provider');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['embed_provider', 'embed_id']);
        });
    }
};
