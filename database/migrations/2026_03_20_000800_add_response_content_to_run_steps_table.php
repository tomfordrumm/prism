<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('run_steps', function (Blueprint $table) {
            $table->longText('response_content')->nullable()->after('response_raw');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('run_steps', function (Blueprint $table) {
            $table->dropColumn('response_content');
        });
    }
};
