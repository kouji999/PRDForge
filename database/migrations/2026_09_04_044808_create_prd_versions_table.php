<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prd_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prd_id')->constrained()->cascadeOnDelete();
            $table->string('version', 20); // v0.1, v1.0
            $table->string('label')->nullable(); // "Initial Draft", "Technical Review"
            $table->json('snapshot'); // immutable full PRD snapshot
            $table->unsignedInteger('section_count')->default(0);
            $table->timestamps();

            $table->unique(['prd_id', 'version']);
            $table->index('prd_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prd_versions');
    }
};
