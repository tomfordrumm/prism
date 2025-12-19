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
        Schema::table('chains', function (Blueprint $table) {
            $table->boolean('is_quick_prompt')->default(false)->after('is_active')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chains', function (Blueprint $table) {
            $table->dropIndex(['is_quick_prompt']);
            $table->dropColumn('is_quick_prompt');
        });
    }
};
