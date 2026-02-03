<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prompt_conversations')) {
            return;
        }

        if (Schema::hasColumn('prompt_conversations', 'agent_id')) {
            return;
        }

        Schema::table('prompt_conversations', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete()->after('target_prompt_version_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('prompt_conversations')) {
            return;
        }

        if (! Schema::hasColumn('prompt_conversations', 'agent_id')) {
            return;
        }

        Schema::table('prompt_conversations', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->dropColumn('agent_id');
        });
    }
};
