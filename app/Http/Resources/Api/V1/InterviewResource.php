<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Interview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InterviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'resume_id' => $this->resume_id,
            'job_description_id' => $this->job_description_id,
            'status' => $this->status->value,
            'total_questions' => Interview::INITIAL_QUESTION_COUNT,
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
        ];
    }
}
