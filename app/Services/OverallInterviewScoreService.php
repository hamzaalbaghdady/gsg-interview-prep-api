<?php

namespace App\Services;

use App\Enums\InterviewAnswerStatus;
use App\Models\Interview;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class OverallInterviewScoreService
{
    public function calculate(Collection $questions): float
    {
        // Check the questions count
        if ($questions->count() !== Interview::INITIAL_QUESTION_COUNT) {
            throw new InvalidArgumentException(
                'The interview must contain exactly '
                    . Interview::INITIAL_QUESTION_COUNT
                    . ' questions.'
            );
        }

        // calculate the overall score
        $scores = $questions->map(function ($question) {
            $answer = $question->answer;

            // check if answer exists and its evaluation is completed
            if (
                $answer === null ||
                $answer->evaluation_status !== InterviewAnswerStatus::COMPLETED
            ) {
                throw new InvalidArgumentException(
                    'All answers must be evaluated before calculating the overall score.'
                );
            }

            // validate each answer score
            if ($answer->score === null || $answer->score < 0 || $answer->score > 10) {
                throw new InvalidArgumentException(
                    'Every answer must have a valid score between 0 and 10.'
                );
            }

            return (float) $answer->score;
        });

        // return the overall score out of 100.00
        return round($scores->avg() * 10, 2);
    }
}
