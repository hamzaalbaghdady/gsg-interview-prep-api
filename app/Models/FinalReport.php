<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['overall_score', 'summary', 'improvement_areas', 'dimensions', 'discrepancy_flag', 'status'])]
class FinalReport extends Model
{
    //cast
    protected function casts(): array
    {
        return [
            'overall_score' => 'float',
            'improvement_areas' => AsArrayObject::class,
            'dimensions' => AsArrayObject::class,
            'discrepancy_flag' => AsArrayObject::class,
            'status' => ReportStatus::class,
        ];
    }

    // Interview Relation
    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }
}
