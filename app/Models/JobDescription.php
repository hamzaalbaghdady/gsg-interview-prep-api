<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'company_name', 'description',])]
class JobDescription extends Model
{

    // User - job relation
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
