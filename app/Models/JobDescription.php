<?php

namespace App\Models;

use App\Enums\ParsingStatus;
use App\Models\GapAnalysis;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'company_name', 'description', 'parsing_status'])]
class JobDescription extends Model
{

    protected function casts(): array
    {
        return [
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
