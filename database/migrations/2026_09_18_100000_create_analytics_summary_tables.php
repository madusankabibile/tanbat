<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Overall daily traffic rollup (views and unique visitors per host per day)
        Schema::create('daily_traffic_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('host', 100)->default('');
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedInteger('uniques')->default(0);
            $table->timestamps();

            $table->unique(['day', 'host'], 'dts_day_host_unique');
            $table->index('day', 'dts_day_index');
            $table->index('host', 'dts_host_index');
        });

        // 2. Per-page daily rollup (exact view counts and unique visitor counts per page)
        Schema::create('daily_page_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('host', 100)->default('');
            $table->string('path', 255);
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedInteger('uniques')->default(0);
            $table->timestamps();

            $table->unique(['day', 'host', 'path'], 'dps_day_host_path_unique');
            $table->index(['day', 'host'], 'dps_day_host_index');
            $table->index('path', 'dps_path_index');
        });

        // 3. Country aggregation rollup
        Schema::create('daily_country_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('host', 100)->default('');
            $table->char('country_code', 2)->default('XX');
            $table->string('country_name', 80)->default('Unknown');
            $table->unsignedInteger('visitors')->default(0);
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamps();

            $table->unique(['day', 'host', 'country_code'], 'dcs_day_host_country_unique');
            $table->index(['day', 'host'], 'dcs_day_host_index');
            $table->index('country_code', 'dcs_country_code_index');
        });

        // 4. External referrer aggregation rollup
        Schema::create('daily_referrer_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('host', 100)->default('');
            $table->string('referrer_host', 150);
            $table->string('referrer_url', 255)->nullable();
            $table->string('target_page', 150)->default('/');
            $table->unsignedInteger('visitors')->default(0);
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamps();

            $table->unique(['day', 'host', 'referrer_host', 'target_page'], 'drs_day_host_ref_page_unique');
            $table->index(['day', 'host'], 'drs_day_host_index');
            $table->index('referrer_host', 'drs_ref_host_index');
        });

        // 5. Device and browser breakdown rollup
        Schema::create('daily_device_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('host', 100)->default('');
            $table->string('device', 50)->default('desktop');
            $table->string('browser', 50)->default('Unknown');
            $table->boolean('is_bot')->default(false);
            $table->unsignedInteger('visitors')->default(0);
            $table->timestamps();

            $table->unique(['day', 'host', 'device', 'browser', 'is_bot'], 'dds_day_host_dev_brow_bot_unique');
            $table->index(['day', 'host'], 'dds_day_host_index');
        });

        // 6. Archived article country views for inactive posts/periods
        Schema::create('article_country_view_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->char('country_code', 2);
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamp('archived_at')->useCurrent();

            $table->unique(['post_id', 'country_code'], 'acvs_post_country_unique');
            $table->index(['country_code', 'views'], 'acvs_country_views_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_country_view_summaries');
        Schema::dropIfExists('daily_device_summaries');
        Schema::dropIfExists('daily_referrer_summaries');
        Schema::dropIfExists('daily_country_summaries');
        Schema::dropIfExists('daily_page_summaries');
        Schema::dropIfExists('daily_traffic_summaries');
    }
};
