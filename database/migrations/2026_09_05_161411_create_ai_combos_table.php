<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A combo is an ordered team of providers. Members have a priority
        // (1 = primary); requests walk the list until one succeeds.
        Schema::create('ai_combos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });

        Schema::create('ai_combo_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_combo_id')->constrained('ai_combos')->cascadeOnDelete();
            $table->foreignId('ai_provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $table->unsignedInteger('priority')->default(1); // 1 = first tried
            $table->timestamps();

            $table->unique(['ai_combo_id', 'ai_provider_id']);
            $table->index(['ai_combo_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_combo_members');
        Schema::dropIfExists('ai_combos');
    }
};
