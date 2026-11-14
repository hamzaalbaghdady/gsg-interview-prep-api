<?php

namespace App\Services;

use App\Enums\InterviewAnswerStatus;
use App\Exceptions\AiServiceException;
use App\Models\Interview;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class PythonInterviewReportService
{
    public function generate(Interview $interview): array
    {
        // Load resume, jobDescription, questions and answers
        $interview->loadMissing([
            'resume',
            'jobDescription',
            'questions.answer',
        ]);

        // sort questions by sequence number
        $questions = $interview->questions
            ->sortBy('sequence_number')
            ->values();

        // check questions count
        if ($questions->count() !== Interview::INITIAL_QUESTION_COUNT) {
            throw new AiServiceException('missing_input');
        }

        // map each question with its answer
        $questionData = $questions->map(function ($question) {
            $answer = $question->answer;

            if (
                $answer === null ||
                $answer->evaluation_status !== InterviewAnswerStatus::COMPLETED
            ) {
                throw new AiServiceException('missing_input');
            }

            return [
                'question_id' => $question->id,
                'sequence_number' => $question->sequence_number,
                'question' => $question->text,
                'answer' => $answer->answer,
                'score' => $answer->score,
                'better_answer' => $answer->better_answer,
            ];
        })->all();

        if ($interview->resume === null || $interview->jobDescription === null) {
            throw new AiServiceException('missing_input');
        }

        $url = config('services.python_ai.interview_report_url');
        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)
            || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new AiServiceException('service_configuration');
        }

        // required data for the AI service
        $payload = [
            'interview_id' => $interview->id,
            'resume_text' => $interview->resume->extracted_text,
            'job_description' => $interview->jobDescription->description,
            'questions' => $questionData,
        ];

        // send request
        $request = Http::acceptJson()
            ->asJson()
            ->connectTimeout(max(1, min((int) config('services.python_ai.connect_timeout', 5), 5)))
            ->timeout(max(1, min((int) config('services.python_ai.timeout', 60), 60)));

        $token = config('services.python_ai.token');

        if (! empty($token)) {
            $request->withToken($token);
        }

        $response = $request->post(
            $url,
            $payload
        );

        if (! $response->successful()) {
            throw AiServiceException::fromResponse($response);
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new AiServiceException('invalid_response');
        }

        // validate response
        $validator = Validator::make($data, [
            'summary' => ['required', 'string'],
            'improvement_areas' => ['required', 'array'],
            'dimensions' => ['required', 'array'],
            'discrepancy_flag' => ['present', 'array'],
        ]);

        if ($validator->fails()) {
            throw new AiServiceException('invalid_response');
        }

        return $validator->validated();
    }
}
