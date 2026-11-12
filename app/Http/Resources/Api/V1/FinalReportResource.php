<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinalReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,
            'interview_id' => $this->interview_id,
            'interview_status' => $this->interview->status->value,

            // All questions with answers
            'questions' => InterviewQuestionResource::collection(
                $this->interview->questions
            ),

            'overall_score' => $this->overall_score,
            'summary' => $this->summary,
            'improvement_areas' => $this->improvement_areas,
            'dimensions' => $this->dimensions,
            'discrepancy_flag' => $this->discrepancy_flag,

            'status' => $this->status?->value,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
