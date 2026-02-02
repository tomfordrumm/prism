<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('run_steps', function (Blueprint $table) {
            $table->foreignId('system_prompt_version_id')
                ->nullable()
                ->after('prompt_version_id')
                ->constrained('prompt_versions')
                ->nullOnDelete();
            $table->foreignId('user_prompt_version_id')
                ->nullable()
                ->after('system_prompt_version_id')
                ->constrained('prompt_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('run_steps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_prompt_version_id');
            $table->dropConstrainedForeignId('system_prompt_version_id');
        });
    }
};
