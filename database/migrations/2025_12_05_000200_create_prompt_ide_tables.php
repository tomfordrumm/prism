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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'name']);
        });

        Schema::create('provider_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('name');
            $table->text('encrypted_api_key');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'provider']);
        });

        Schema::create('prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('variables')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'project_id']);
        });

        Schema::create('prompt_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prompt_template_id')->constrained('prompt_templates')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->text('content');
            $table->text('changelog')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['prompt_template_id', 'version']);
            $table->index(['tenant_id', 'prompt_template_id']);
        });

        Schema::create('chains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'project_id']);
        });

        Schema::create('chain_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chain_id')->constrained('chains')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('order_index');
            $table->foreignId('provider_credential_id')->constrained('provider_credentials')->cascadeOnDelete();
            $table->string('model_name');
            $table->json('model_params')->nullable();
            $table->json('messages_config');
            $table->json('output_schema')->nullable();
            $table->boolean('stop_on_validation_error')->default(false);
            $table->timestamps();

            $table->unique(['chain_id', 'order_index']);
            $table->index(['tenant_id', 'chain_id']);
        });

        Schema::create('datasets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'project_id']);
        });

        Schema::create('test_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dataset_id')->constrained('datasets')->cascadeOnDelete();
            $table->string('name');
            $table->json('input_variables');
            $table->json('expected_output')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'dataset_id']);
        });

        Schema::create('runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('chain_id')->constrained('chains')->cascadeOnDelete();
            $table->foreignId('dataset_id')->nullable()->constrained('datasets')->nullOnDelete();
            $table->foreignId('test_case_id')->nullable()->constrained('test_cases')->nullOnDelete();
            $table->json('input');
            $table->json('chain_snapshot');
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->unsignedInteger('total_tokens_in')->nullable();
            $table->unsignedInteger('total_tokens_out')->nullable();
            $table->decimal('total_cost', 16, 6)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->index(['tenant_id', 'project_id']);
            $table->index(['tenant_id', 'chain_id']);
            $table->index(['status']);
        });

        Schema::create('run_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('run_id')->constrained('runs')->cascadeOnDelete();
            $table->foreignId('chain_node_id')->constrained('chain_nodes')->cascadeOnDelete();
            $table->unsignedInteger('order_index');
            $table->json('request_payload');
            $table->json('response_raw');
            $table->json('parsed_output')->nullable();
            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('validation_errors')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique(['run_id', 'order_index']);
            $table->index(['tenant_id', 'run_id']);
            $table->index(['tenant_id', 'chain_node_id']);
        });

        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('run_id')->constrained('runs')->cascadeOnDelete();
            $table->foreignId('run_step_id')->nullable()->constrained('run_steps')->nullOnDelete();
            $table->string('type');
            $table->integer('rating')->nullable();
            $table->text('comment')->nullable();
            $table->text('suggested_prompt_content')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'run_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback');
        Schema::dropIfExists('run_steps');
        Schema::dropIfExists('runs');
        Schema::dropIfExists('test_cases');
        Schema::dropIfExists('datasets');
        Schema::dropIfExists('chain_nodes');
        Schema::dropIfExists('chains');
        Schema::dropIfExists('prompt_versions');
        Schema::dropIfExists('prompt_templates');
        Schema::dropIfExists('provider_credentials');
        Schema::dropIfExists('projects');
    }
};
