<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->integer('conversations_count')->default(0);
            $table->integer('messages_count')->default(0);
            $table->bigInteger('tokens_in')->default(0);
            $table->bigInteger('tokens_out')->default(0);
            $table->timestamps();

            $table->unique(['agent_id', 'date']);
            $table->index(['agent_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_analytics');
    }
};
