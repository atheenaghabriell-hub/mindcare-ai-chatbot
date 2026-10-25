<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('gemini_data_sharing_consented_at')->nullable();
            $table->boolean('context_memory_enabled')->default(false);
            $table->timestamp('crisis_review_consented_at')->nullable();
            $table->boolean('is_admin')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'gemini_data_sharing_consented_at',
                'context_memory_enabled',
                'crisis_review_consented_at',
                'is_admin',
            ]);
        });
    }
};
