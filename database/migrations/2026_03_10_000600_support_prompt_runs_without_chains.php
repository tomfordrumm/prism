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
        Schema::table('runs', function (Blueprint $table) {
            $table->foreignId('chain_id')->nullable()->change();
        });

        Schema::table('run_steps', function (Blueprint $table) {
            $table->foreignId('provider_credential_id')
                ->nullable()
                ->after('chain_node_id')
                ->constrained('provider_credentials')
                ->nullOnDelete();
            $table->foreignId('prompt_version_id')
                ->nullable()
                ->after('provider_credential_id')
                ->constrained('prompt_versions')
                ->nullOnDelete();
            $table->foreignId('chain_node_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('run_steps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prompt_version_id');
            $table->dropConstrainedForeignId('provider_credential_id');
            $table->foreignId('chain_node_id')->nullable(false)->change();
        });

        Schema::table('runs', function (Blueprint $table) {
            $table->foreignId('chain_id')->nullable(false)->change();
        });
    }
};
