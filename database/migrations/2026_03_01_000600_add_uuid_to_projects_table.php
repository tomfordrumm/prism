<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->after('id');
        });

        DB::table('projects')
            ->whereNull('uuid')
            ->orderBy('id')
            ->chunkById(500, function ($projects): void {
                foreach ($projects as $project) {
                    DB::table('projects')
                        ->where('id', $project->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            });

        Schema::table('projects', function (Blueprint $table): void {
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
