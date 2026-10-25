<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('title', 45);
            $table->string('file_path', 100);
            $table->char('file_hash', 64);
            $table->text('extracted_text')->nullable();
            $table->string('parsing_status', 45)->default('pending');
            $table->timestamps();

            $table->unique(['user_id', 'file_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resumes');
    }
};
