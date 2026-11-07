<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resume_id')->constrained()->restrictOnDelete();
            $table->foreignId('job_description_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['preparing', 'in_progress', 'completed', 'failed']);
            $table->timestamp('started_at', 5)->nullable();
            $table->timestamp('completed_at', 5)->nullable();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};
