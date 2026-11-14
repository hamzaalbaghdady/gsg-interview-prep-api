<?php

namespace App\Services;

use App\Exceptions\AiServiceException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class PythonInterviewAnswerService
{
    public function evaluateAnswer(int $interviewId, int $questionId, string $answer): array
    {
        $url = config('services.python_ai.interview_answer_url');

        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)
            || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new AiServiceException('service_configuration');
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
            throw AiServiceException::fromResponse($response);
        }

        $payload = $response->json();

        if (! is_array($payload) || ($payload['question_id'] ?? $payload['id'] ?? null) !== $questionId) {
            throw new AiServiceException('invalid_response');
        }

        $validator = Validator::make($payload, [
            'score' => ['required', 'numeric', 'between:0,10'],
            'better_answer' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            throw new AiServiceException('invalid_response');
        }

        return $validator->validated();
    }
}
