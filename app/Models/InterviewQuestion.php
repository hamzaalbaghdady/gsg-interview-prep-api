<?php

namespace App\Models;

use App\Models\Interview;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sequence_number', 'text', 'type'])]
class InterviewQuestion extends Model
{

    // Interview relation
    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }

    // Answer relation
}
