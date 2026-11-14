<?php

namespace App\Models;

use App\Enums\InterviewStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['resume_id', 'job_description_id', 'status'])]
#[Hidden(['ai_run', 'ai_error_code'])]
class Interview extends Model
{
    public const INITIAL_QUESTION_COUNT = 10;

    public const MAX_FOLLOW_UP_QUESTIONS = 3;

    public $timestamps = false;

    protected $attributes = [
        'status' => InterviewStatus::PREPARING->value,
    ];

    protected function casts(): array
    {
        return [
            'ai_run' => 'integer',
            'status' => InterviewStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resume(): BelongsTo
    {
        return $this->belongsTo(Resume::class);
    }

    public function jobDescription(): BelongsTo
    {
        return $this->belongsTo(JobDescription::class);
    }

    // Interview - Question relationship
    public function questions(): HasMany
    {
        return $this->hasMany(InterviewQuestion::class)
            ->orderBy('sequence_number', 'asc');
    }

    // Interview - Report relation
    public function report(): HasOne
    {
        return $this->hasOne(FinalReport::class);
    }
}
