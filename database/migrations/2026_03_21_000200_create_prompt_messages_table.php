<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')
                ->constrained('prompt_conversations')
                ->cascadeOnDelete();
            $table->string('role');
            $table->text('content');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_messages');
    }
};
