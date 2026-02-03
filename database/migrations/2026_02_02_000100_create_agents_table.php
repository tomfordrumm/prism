<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            // Basic info
            $table->string('name');
            $table->text('description')->nullable();

            // System prompt (inline only for v1)
            $table->text('system_prompt_content');

            // Model configuration
            $table->foreignId('provider_credential_id')->constrained()->cascadeOnDelete();
            $table->string('model_name');
            $table->json('model_params')->nullable();

            // Context management
            $table->integer('max_context_messages')->default(20);

            // Tool config (prepared for v2)
            $table->json('tool_config')->nullable();

            // Status & analytics
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->integer('total_conversations')->default(0);
            $table->integer('total_messages')->default(0);
            $table->bigInteger('total_tokens_in')->default(0);
            $table->bigInteger('total_tokens_out')->default(0);

            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'project_id']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
