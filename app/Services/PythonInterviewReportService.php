<?php

namespace App\Services;

use App\Enums\InterviewAnswerStatus;
use App\Models\Interview;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use UnexpectedValueException;

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
            throw new InvalidArgumentException(
                'The interview does not have the required number of questions.'
            );
        }

        // map each question with its answer
        $questionData = $questions->map(function ($question) {
            $answer = $question->answer;

            if (
                $answer === null ||
                $answer->evaluation_status !== InterviewAnswerStatus::COMPLETED
            ) {
                throw new InvalidArgumentException(
                    'All answers must be evaluated before generating the report.'
                );
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
            ->connectTimeout(config('services.python_ai.connect_timeout', 5))
            ->timeout(config('services.python_ai.timeout', 60));

        $token = config('services.python_ai.token');

        if (!empty($token)) {
            $request->withToken($token);
        }

        $response = $request->post(
            config('services.python_ai.interview_report_url'),
            $payload
        )->throw();

        $data = $response->json();

        if (! is_array($data)) {
            throw new UnexpectedValueException(
                'The Python AI service returned an invalid response.'
            );
        }

        // validate response
        return Validator::make($data, [
            'summary' => ['required', 'string'],
            'improvement_areas' => ['required', 'array'],
            'dimensions' => ['required', 'array'],
            'discrepancy_flag' => ['present', 'array'],
        ])->validate();
    }
}
