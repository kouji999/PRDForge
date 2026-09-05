<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('ai_providers')->nullOnDelete();
            $table->string('type', 50); // conversation|extraction|prd_generation|section_action|prd_review
            $table->string('status')->default('pending'); // pending|completed|failed
            $table->string('error_category')->nullable(); // timeout|invalid_key|rate_limited|provider_error|invalid_output
            $table->unsignedInteger('latency_ms')->nullable();
            $table->json('meta')->nullable(); // model, tokens, request_id — never prompts, never keys
            $table->timestamps();

            $table->index(['user_id', 'type', 'status']);
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generations');
    }
};
