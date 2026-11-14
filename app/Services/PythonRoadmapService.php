<?php

namespace App\Services;

use App\Exceptions\AiServiceException;
use App\Models\GapAnalysis;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class PythonRoadmapService
{
    public function generate(GapAnalysis $analysis): array
    {
        $url = config('services.python_ai.roadmap_url');

        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)
            || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new AiServiceException('service_configuration');
        }

        if ($analysis->jobDescription === null) {
            throw new AiServiceException('missing_input');
        }

        $request = Http::acceptJson()
            ->connectTimeout(5)
            ->timeout(30);

        if ($token = config('services.python_ai.token')) {
            $request = $request->withToken($token);
        }

        // Post the calculated missing skills analysis dictionary directly to Python
        $response = $request->post($url, [
            'job_title' => $analysis->jobDescription->title,
            'missing_skills' => $analysis->analysis['missing_skills'] ?? [],
            'matching_skills' => $analysis->analysis['matching_skills'] ?? [],
        ]);

        if (! $response->successful()) {
            throw AiServiceException::fromResponse($response);
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new AiServiceException('invalid_response');
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
            throw new AiServiceException('invalid_response');
        }

        return $validator->validated();
    }
}
