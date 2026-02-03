<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('type');
            $table->foreignId('run_id')->nullable()->constrained('runs')->nullOnDelete();
            $table->foreignId('run_step_id')->nullable()->constrained('run_steps')->nullOnDelete();
            $table->foreignId('target_prompt_version_id')->nullable()->constrained('prompt_versions')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['tenant_id', 'project_id']);
            $table->index(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_conversations');
    }
};
