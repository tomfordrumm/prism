<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('chain_nodes', function (Blueprint $table) {
            $table->text('output_schema_definition')->nullable()->after('output_schema');
        });
    }

    public function down(): void
    {
        Schema::table('chain_nodes', function (Blueprint $table) {
            $table->dropColumn('output_schema_definition');
        });
    }
};
