<?php

namespace App\Services;

use App\Models\Interview;
use App\Models\GapAnalysis; // 👈 Import the model dependency cleanly
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class PythonInterviewService
{
    /**
     * Call the Python AI service to generate the initial batch of 10 interview questions.
     */
    public function generateBaseQuestions(Interview $interview): array
    {
        // get the url from env
        $url = config('services.python_ai.interview_url');

        // check url
        if (!is_string($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Python AI interview endpoint configuration is missing or invalid.');
        }

        // Fetch relationship text dependencies securely
        $resumeText = $interview->resume?->extracted_text;
        $jobDescriptionText = $interview->jobDescription?->description;
        $jobTitle = $interview->jobDescription?->title;

        // check if text fields are blank
        if (blank($resumeText) || blank($jobDescriptionText)) {
            throw new RuntimeException('Missing required resume or job description content payload for AI analysis.');
        }

        // get gab analysis for job & resume
        $gapAnalysis = GapAnalysis::query()
            ->where('job_description_id', $interview->job_description_id)
            ->where('resume_id', $interview->resume_id)
            ->first();

        // check if analysis doesn't exist
        if ($gapAnalysis === null) {
            throw new RuntimeException('Cannot initialize interview: Missing baseline persistent Gap Analysis record.');
        }

        // Extract missing and matched skills from analysis
        $missingSkills = $gapAnalysis->analysis['missing_skills'] ?? [];
        $matchingSkills = $gapAnalysis->analysis['matching_skills'] ?? [];

        // prepare request headers
        $request = Http::acceptJson()
            ->connectTimeout(5)
            ->timeout(45);

        // use token if any
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
            'interview_id' => $interview->id
        ]);

        // check response status
        if (!$response->successful()) {
            throw new RuntimeException("Python interview service failed with HTTP code {$response->status()}.");
        }

        // get response as json
        $payload = $response->json();

        if (!is_array($payload)) {
            throw new RuntimeException('Python interview service returned an invalid or corrupt data structure.');
        }

        // make sure to trim question text
        $payload['questions'] = array_map(
            fn($question) => is_string($question) ? trim($question) : $question,
            $payload['questions'] ?? []
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
            throw new RuntimeException(
                'Python AI service payload failed structural validation checks: '
                    . json_encode($validator->errors()->toArray())
            );
        }

        return $validator->validated();
    }
}
