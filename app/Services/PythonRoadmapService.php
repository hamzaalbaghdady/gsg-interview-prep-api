<?php

namespace App\Services;

use App\Models\GapAnalysis;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class PythonRoadmapService
{
    public function generate(GapAnalysis $analysis): array
    {
        $url = config('services.python_ai.roadmap_url', 'http://localhost:8000/api/v1/generate-roadmap');

        $request = Http::acceptJson()
            ->connectTimeout(5)
            ->timeout(30);

        if ($token = config('services.python_ai.token')) {
            $request = $request->withToken($token);
        }

        // Post the calculated missing skills analysis dictionary directly to Python
        $response = $request->post($url, [
            'match_percentage' => $analysis->match_percentage,
            'missing_skills' => $analysis->analysis['missing_skills'] ?? [],
        ]);

        if (!$response->successful()) {
            throw new RuntimeException("Python roadmap service returned HTTP {$response->status()} state failure.");
        }

        $payload = $response->json();

        if (!is_array($payload)) {
            throw new RuntimeException('Python roadmap service returned an invalid JSON data payload.');
        }

        // Validate response
        $validator = Validator::make($payload, [
            'roadmaps' => ['present', 'array', 'list'],
            'roadmaps.*' => ['required', 'array:skill_id,skill_name,category,importance,priority,title,estimated_duration,steps,resources'],

            // Root level object properties
            'roadmaps.*.skill_id' => ['required', 'integer'],
            'roadmaps.*.skill_name' => ['required', 'string', 'max:45'],
            'roadmaps.*.category' => ['required', 'string'],
            'roadmaps.*.importance' => ['required', 'numeric'],
            'roadmaps.*.priority' => ['required', 'integer'],
            'roadmaps.*.title' => ['required', 'string'],
            'roadmaps.*.estimated_duration' => ['required', 'string'],

            // Steps array validation
            'roadmaps.*.steps' => ['required', 'array', 'list'],
            'roadmaps.*.steps.*' => ['required', 'array:week,title,topics'],
            'roadmaps.*.steps.*.week' => ['required', 'integer'],
            'roadmaps.*.steps.*.title' => ['required', 'string'],
            'roadmaps.*.steps.*.topics' => ['required', 'array', 'list'],
            'roadmaps.*.steps.*.topics.*' => ['required', 'string'],

            // Resources array validation
            'roadmaps.*.resources' => ['present', 'array'],
            'roadmaps.*.resources.*' => ['required', 'array:title,url'],
            'roadmaps.*.resources.*.title' => ['required', 'string'],
            'roadmaps.*.resources.*.url' => ['required', 'url'],
        ]);


        if ($validator->fails()) {
            throw new RuntimeException('Python roadmap service response failed schema validation rules.');
        }

        return $validator->validated();
    }
}
