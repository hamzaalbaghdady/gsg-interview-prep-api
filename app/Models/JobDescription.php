<?php

namespace App\Models;

use App\Enums\ParsingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'company_name', 'description', 'parsing_status'])]
#[Hidden(['ai_run', 'ai_error_code'])]
class JobDescription extends Model
{
    protected function casts(): array
    {
        return [
            'ai_run' => 'integer',
            'parsing_status' => ParsingStatus::class,
        ];
    }

    // User - job relation
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Job - Skills realation
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'job_descriptions_skills')
            ->withPivot('importance')
            ->withCasts(['importance' => 'float']);
    }

    public function gapAnalyses(): HasMany
    {
        return $this->hasMany(GapAnalysis::class);
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }
}
