<?php

namespace App\Services;

use App\Exceptions\AiServiceException;
use App\Models\GapAnalysis;
use App\Models\Interview;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class PythonInterviewService
{
    /**
     * Call the Python AI service to generate the initial batch of 10 interview questions.
     */
    public function generateBaseQuestions(Interview $interview): array
    {
        // get the url from env
        $url = config('services.python_ai.interview_url');

        // Fetch relationship text dependencies securely
        $resumeText = $interview->resume?->extracted_text;
        $jobDescriptionText = $interview->jobDescription?->description;
        $jobTitle = $interview->jobDescription?->title;

        // check if text fields are blank
        if (blank($resumeText) || blank($jobDescriptionText)) {
            throw new AiServiceException('missing_input');
        }

        // get gab analysis for job & resume
        $gapAnalysis = GapAnalysis::query()
            ->where('job_description_id', $interview->job_description_id)
            ->where('resume_id', $interview->resume_id)
            ->first();

        // check if analysis doesn't exist
        if ($gapAnalysis === null) {
            throw new AiServiceException('missing_input');
        }

        // Extract missing and matched skills from analysis
        $missingSkills = $gapAnalysis->analysis['missing_skills'] ?? [];
        $matchingSkills = $gapAnalysis->analysis['matching_skills'] ?? [];

        // prepare request headers
        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)
            || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new AiServiceException('service_configuration');
        }

        $request = Http::acceptJson()->connectTimeout(5)->timeout(max(1, min((int) config('services.python_ai.timeout', 45), 45)));

        if ($token = config('services.python_ai.token')) {
            $request = $request->withToken($token);
        }

        // send request payload as AI service requires
        $response = $request->post($url, [
            'resume' => $resumeText,
            'job_description' => $jobDescriptionText,
            'job_title' => $jobTitle,
            'missing_skills' => $missingSkills,
            'matching_skills' => $matchingSkills,
            'interview_id' => $interview->id,
        ]);

        // check response status
        if (! $response->successful()) {
            throw AiServiceException::fromResponse($response);
        }

        // get response as json
        $payload = $response->json();

        if (! is_array($payload)) {
            throw new AiServiceException('invalid_response');
        }

        // make sure to trim question text
        $payload['questions'] = array_map(
            fn ($question) => is_string($question) ? trim($question) : $question,
            is_array($payload['questions'] ?? null) ? $payload['questions'] : []
        );

        // Ensure the Python system returned an array of exactly 10 questions
        $validator = Validator::make($payload, [
            'questions' => ['required', 'array', 'size:10'],
            'questions.*' => ['required', 'array'],
            'questions.*.text' => ['required', 'string', 'min:10', 'max:5000', 'distinct'],
            'questions.*.id' => ['nullable', 'integer'],
            'questions.*.type' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            throw new AiServiceException('invalid_response');
        }

        return $validator->validated();
    }
}
