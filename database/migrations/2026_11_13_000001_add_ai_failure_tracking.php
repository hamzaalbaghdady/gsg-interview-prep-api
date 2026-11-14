<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = ['resumes', 'job_descriptions', 'gap_analyses', 'interviews', 'interview_answers', 'final_reports'];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('ai_error_code')->nullable();
                $table->unsignedInteger('ai_run')->default(0);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['ai_error_code', 'ai_run']));
        }
    }
};
