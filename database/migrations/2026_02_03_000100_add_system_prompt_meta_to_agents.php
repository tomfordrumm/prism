<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->enum('system_prompt_mode', ['template', 'inline'])->default('inline')->after('system_prompt_content');
            $table->foreignId('system_prompt_template_id')->nullable()->constrained('prompt_templates')->nullOnDelete()->after('system_prompt_mode');
            $table->foreignId('system_prompt_version_id')->nullable()->constrained('prompt_versions')->nullOnDelete()->after('system_prompt_template_id');
            $table->text('system_inline_content')->nullable()->after('system_prompt_version_id');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropForeign(['system_prompt_template_id']);
            $table->dropForeign(['system_prompt_version_id']);
            $table->dropColumn(['system_prompt_mode', 'system_prompt_template_id', 'system_prompt_version_id', 'system_inline_content']);
        });
    }
};
