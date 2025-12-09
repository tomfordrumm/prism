<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chain_nodes', function (Blueprint $table): void {
            if (Schema::hasColumn('chain_nodes', 'llm_model_id')) {
                $table->dropForeign(['llm_model_id']);
                $table->dropColumn('llm_model_id');
            }

            if (! Schema::hasColumn('chain_nodes', 'provider_credential_id')) {
                $table->foreignId('provider_credential_id')
                    ->after('order_index')
                    ->constrained('provider_credentials')
                    ->cascadeOnDelete();
            }

            if (! Schema::hasColumn('chain_nodes', 'model_name')) {
                $table->string('model_name')->after('provider_credential_id');
            }

            if (! Schema::hasColumn('chain_nodes', 'model_params')) {
                $table->json('model_params')->nullable()->after('model_name');
            }
        });

        Schema::dropIfExists('llm_models');
    }

    public function down(): void
    {
        Schema::create('llm_models', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_credential_id')->constrained('provider_credentials')->cascadeOnDelete();
            $table->string('name');
            $table->string('display_name');
            $table->json('default_params')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'provider_credential_id']);
        });

        Schema::table('chain_nodes', function (Blueprint $table): void {
            if (Schema::hasColumn('chain_nodes', 'provider_credential_id')) {
                $table->dropForeign(['provider_credential_id']);
                $table->dropColumn('provider_credential_id');
            }

            if (Schema::hasColumn('chain_nodes', 'model_name')) {
                $table->dropColumn('model_name');
            }

            if (Schema::hasColumn('chain_nodes', 'model_params')) {
                $table->dropColumn('model_params');
            }

            if (! Schema::hasColumn('chain_nodes', 'llm_model_id')) {
                $table->foreignId('llm_model_id')->nullable()->constrained('llm_models')->nullOnDelete();
            }
        });
    }
};
