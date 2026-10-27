<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobDescriptionResource extends JsonResource
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
            'title' => $this->title,
            'company_name' => $this->company_name,
            'parsing_status' => $this->parsing_status->value,

            // PERFORMANCE OPTIMIZATION: Only return the description text for show
            'description' => $this->when(
                $request->routeIs('*job-descriptions.show'),
                fn() => $this->description
            ),

            // EAGER LOAD PROTECTION: Only map and return skills when they have been loaded by the controller
            'skills' => $this->whenLoaded('skills', fn() => $this->skills->map(fn($skill) => [
                'id' => $skill->id,
                'name' => $skill->name,
                'category' => $skill->category,
                'importance' => $skill->pivot->importance === null
                    ? null
                    : (float) $skill->pivot->importance,
            ])),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
