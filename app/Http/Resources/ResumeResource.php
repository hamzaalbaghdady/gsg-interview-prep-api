<?php

namespace App\Http\Resources;

use App\Models\Resume;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResumeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'parsing_status' => $this->parsing_status,
            'processing_error' => $this->parsing_status === Resume::FAILED
                ? 'Resume processing failed. Please try again later.'
                : null,
            'extracted_text' => $this->when(
                $request->routeIs('resumes.show'),
                fn () => $this->extracted_text
            ),
            'skills' => $this->whenLoaded('skills', fn () => $this->skills->map(fn ($skill) => [
                'id' => $skill->id,
                'name' => $skill->name,
                'category' => $skill->category,
                'confidence_score' => $skill->pivot->confidence_score === null
                    ? null
                    : (float) $skill->pivot->confidence_score,
            ])),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
