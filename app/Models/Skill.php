<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'category'])]
class Skill extends Model
{
    public $timestamps = false;

    public function resumes(): BelongsToMany
    {
        return $this->belongsToMany(Resume::class, 'resume_skills')
            ->withPivot('confidence_score');
    }
}
