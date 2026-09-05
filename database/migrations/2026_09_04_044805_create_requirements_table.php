<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50)->default('functional');
            $table->string('title');
            $table->text('content');
            $table->string('status')->default('proposed'); // proposed|confirmed|rejected|needs_review
            $table->string('source', 20)->default('extracted'); // extracted|manual
            $table->string('priority', 20)->default('medium'); // low|medium|high|critical
            $table->softDeletes();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requirements');
    }
};
