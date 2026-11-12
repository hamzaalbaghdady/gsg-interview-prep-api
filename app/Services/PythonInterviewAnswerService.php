<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class PythonInterviewAnswerService
{
    public function evaluateAnswer(int $interviewId, int $questionId, string $answer): array
    {
        $url = config('services.python_ai.interview_answer_url');

        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Python interview answer endpoint configuration is missing or invalid.');
        }

        $request = Http::acceptJson()->connectTimeout(5)->timeout(45);

        if ($token = config('services.python_ai.token')) {
            $request = $request->withToken($token);
        }

        $response = $request->post($url, [
            'interview_id' => $interviewId,
            'id' => $questionId,
            'text' => $answer,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("Python interview evaluation failed with HTTP code {$response->status()}.");
        }

        $payload = $response->json();

        if (! is_array($payload) || ($payload['question_id'] ?? $payload['id'] ?? null) !== $questionId) {
            throw new RuntimeException('Python evaluation response does not match the submitted answer.');
        }

        $validator = Validator::make($payload, [
            'score' => ['required', 'numeric', 'between:0,10'],
            'better_answer' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            throw new RuntimeException('Python evaluation response has an invalid format.');
        }

        return $validator->validated();
    }
}
