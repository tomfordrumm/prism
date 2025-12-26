<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('run_steps', function (Blueprint $table) {
            $table->unsignedInteger('retry_count')->nullable()->after('duration_ms');
            $table->json('retry_reasons')->nullable()->after('retry_count');
        });
    }

    public function down(): void
    {
        Schema::table('run_steps', function (Blueprint $table) {
            $table->dropColumn(['retry_count', 'retry_reasons']);
        });
    }
};
