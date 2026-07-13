<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // tanbat.com and omrms.com are one codebase, one public_html and one
        // database, and both visitor tables recorded only the *path* — so no
        // query could say which site a view actually reached. Record the request
        // host on both, and the admin can split traffic by domain honestly.
        //
        // Rows written before this migration keep '' (unknown host): they belong
        // to neither site and are counted as neither.
        Schema::table('visitors', function (Blueprint $table) {
            $table->string('host', 100)->default('')->after('ip_address');
            $table->index('host');
        });

        Schema::table('visitor_page_views', function (Blueprint $table) {
            $table->string('host', 100)->default('')->after('visitor_token');
        });

        // The upsert key has to carry the host too: one visitor reading the same
        // path on both sites would otherwise collide into a single row, and whose
        // view it was would depend on who got there first.
        //
        // Key size: (40 + 100 + 255) * 4 bytes + 3 (DATE) = 1583, still under
        // InnoDB's 3072-byte DYNAMIC row-format limit.
        Schema::table('visitor_page_views', function (Blueprint $table) {
            $table->dropUnique('vpv_token_path_day_unique');
            $table->unique(['visitor_token', 'host', 'path', 'day'], 'vpv_token_host_path_day_unique');
            $table->index(['day', 'host'], 'vpv_day_host_index');
        });
    }

    public function down(): void
    {
        Schema::table('visitor_page_views', function (Blueprint $table) {
            $table->dropIndex('vpv_day_host_index');
            $table->dropUnique('vpv_token_host_path_day_unique');
            $table->unique(['visitor_token', 'path', 'day'], 'vpv_token_path_day_unique');
            $table->dropColumn('host');
        });

        Schema::table('visitors', function (Blueprint $table) {
            $table->dropIndex(['host']);
            $table->dropColumn('host');
        });
    }
};
