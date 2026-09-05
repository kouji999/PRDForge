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
            $table->string('request_id', 36)->index();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('ai_providers')->nullOnDelete();
            $table->string('provider_name');
            $table->string('model');
            $table->string('operation', 50); // chat|test_connection|generate|extract
            $table->string('status', 20); // success|error
            $table->string('error_category', 50)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->json('context')->nullable(); // sanitized metadata only
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('operation');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
