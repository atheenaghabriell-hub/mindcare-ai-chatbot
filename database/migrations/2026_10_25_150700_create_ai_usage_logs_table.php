<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('model', 100);
            $table->string('status', 20);
            $table->unsignedInteger('duration_ms');
            $table->unsignedSmallInteger('input_characters');
            $table->unsignedSmallInteger('output_characters')->nullable();
            $table->boolean('context_memory_used')->default(false);
            $table->dateTime('occurred_at')->index();
            $table->timestamps();

            $table->index(['status', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
