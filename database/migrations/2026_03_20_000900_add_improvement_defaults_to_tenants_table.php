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
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('improvement_provider_credential_id')
                ->nullable()
                ->after('name')
                ->constrained('provider_credentials')
                ->nullOnDelete();
            $table->string('improvement_model_name')->nullable()->after('improvement_provider_credential_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('improvement_provider_credential_id');
            $table->dropColumn('improvement_model_name');
        });
    }
};
