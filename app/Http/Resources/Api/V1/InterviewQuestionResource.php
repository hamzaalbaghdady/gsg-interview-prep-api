<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InterviewQuestionResource extends JsonResource
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
            'sequence_number' => (int) $this->sequence_number,
            'text' => $this->text,
            'type' => $this->type,

            // Conditionally load the answer payload if relation is eager-loaded
            'answer' => $this->whenLoaded(
                'answer',
                fn() => $this->answer ? [
                    'id' => $this->answer->id,
                    'text' => $this->answer->answer,
                    'evaluation_status' => $this->answer->evaluation_status?->value,
                    'score' => $this->answer->score,
                    'better_answer' => $this->answer->better_answer,
                    'created_at' => $this->answer->created_at?->toISOString(),
                ] : null
            ),
        ];
    }
}
