<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gap_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resume_id')->constrained('resumes')->cascadeOnDelete();
            $table->foreignId('job_description_id')->constrained('job_descriptions')->cascadeOnDelete();

            $table->decimal('match_percentage', 5, 2);
            $table->enum('roadmap_status', ['pending', 'processing', 'completed', 'failed'])->default('pending'); // Use your ParsingStatus Enum


            $table->json('analysis');
            $table->json('roadmap')->nullable();

            $table->timestamps();

            // One resume can only have exactly one analysis profile per job posting
            $table->unique(['resume_id', 'job_description_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gap_analyses');
    }
};
