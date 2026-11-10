<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_question_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('answer');
            $table->string('evaluation_status')->default('pending');
            $table->double('score')->nullable();
            $table->text('better_answer')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_answers');
    }
};
