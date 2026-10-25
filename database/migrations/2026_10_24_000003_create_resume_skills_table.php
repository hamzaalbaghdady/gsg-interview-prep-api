<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resume_skills', function (Blueprint $table) {
            $table->foreignId('resume_id')->constrained()->restrictOnDelete();
            $table->foreignId('skill_id')->constrained()->restrictOnDelete();
            $table->decimal('confidence_score', 3, 2)->nullable();

            $table->primary(['resume_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resume_skills');
    }
};
