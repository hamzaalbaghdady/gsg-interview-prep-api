<?php

namespace App\Models;

use App\Enums\InterviewAnswerStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['answer'])]
class InterviewAnswer extends Model
{
    protected $attributes = [
        'evaluation_status' => InterviewAnswerStatus::PENDING->value,
    ];

    protected function casts(): array
    {
        return [
            'evaluation_status' => InterviewAnswerStatus::class,
            'score' => 'float',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(InterviewQuestion::class, 'interview_question_id');
    }
}
