<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('attachment_path')->nullable()->after('body');
            $table->string('attachment_type', 16)->nullable()->after('attachment_path');
            $table->timestamp('delivered_at')->nullable()->after('read_at');
            $table->index(['conversation_id', 'delivered_at']);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'delivered_at']);
            $table->dropColumn(['attachment_path', 'attachment_type', 'delivered_at']);
        });
    }
};
