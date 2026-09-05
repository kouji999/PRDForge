<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_contexts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('problem')->nullable();
            $table->text('target_users')->nullable();
            $table->text('product_concept')->nullable();
            $table->json('core_features')->nullable();
            $table->string('platform')->nullable();
            $table->text('constraints')->nullable();
            $table->json('goals')->nullable();
            $table->text('mvp_scope')->nullable();
            $table->timestamp('ai_extracted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_contexts');
    }
};
