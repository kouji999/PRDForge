<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prd_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prd_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100); // overview, problem, goals...
            $table->string('title');
            $table->text('content');
            $table->json('data')->nullable(); // structured payload (tables, lists, metrics)
            $table->unsignedInteger('order')->default(0);
            $table->string('status')->default('draft'); // draft|reviewed|approved
            $table->timestamps();

            $table->unique(['prd_id', 'key']);
            $table->index(['prd_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prd_sections');
    }
};
