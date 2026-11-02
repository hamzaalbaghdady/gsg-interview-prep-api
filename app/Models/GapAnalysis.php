<?php

namespace App\Models;

use App\Enums\ParsingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['resume_id', 'job_description_id', 'match_percentage', 'roadmap_status', 'analysis', 'roadmap'])]
class GapAnalysis extends Model
{
    protected function casts(): array
    {
        return [
            'roadmap_status' => ParsingStatus::class,
            'analysis' => AsArrayObject::class,
            'roadmap' => AsArrayObject::class
        ];
    }

    // JobDescription relationship
    public function jobDescription(): BelongsTo
    {
        return $this->belongsTo(JobDescription::class, 'job_description_id');
    }

    // Resume relationship
    public function resume(): BelongsTo
    {
        return $this->belongsTo(Resume::class, 'resume_id');
    }
}
