<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InterviewAnswerStatus;
use App\Enums\InterviewStatus;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\FinalReportResource;
use App\Models\Interview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinalReportController extends Controller
{
    public function show(Request $request, Interview $interview): JsonResponse
    {
        // Check if user owns the interview
        abort_unless(
            $request->user()->id === $interview->user_id,
            404,
            'Resource not found.'
        );

        // check if interview is failed
        if ($interview->status === InterviewStatus::FAILED) {
            abort(409, 'This interview is failed, try to start a new one please.');
        }

        // check if interview is not completed
        if ($interview->status !== InterviewStatus::COMPLETED) {
            abort(409, 'This interview must be completed in order to generate the Report');
        }

        // Check if all questions have been answered
        if ($interview->questions()->whereDoesntHave('answer')->exists()) {
            abort(409, 'Finish answering all questions first.');
        }

        // Check if any answer evaluation has failed.
        if ($interview->questions()
            ->whereHas('answer', function ($query) {
                $query->where(
                    'evaluation_status',
                    InterviewAnswerStatus::FAILED->value
                );
            })
            ->exists()
        ) {
            abort(409, 'One or more answers failed evaluation.');
        }

        // Check if any answer is still pending or being evaluated.
        if ($interview->questions()
            ->whereHas('answer', function ($query) {
                $query->whereIn('evaluation_status', [
                    InterviewAnswerStatus::PENDING->value,
                    InterviewAnswerStatus::IN_PROGRESS->value,
                ]);
            })
            ->exists()
        ) {
            return response()->json([
                'message' => 'Some answers are waiting for evaluation.',
            ], 202);
        }

        // Load the report, questions, and their answers
        $interview->load(['report', 'questions.answer',]);

        $report = $interview->report;

        // Report has not been created yet
        if ($report === null) {
            return response()->json([
                'message' => 'The report is not available yet.',
                'report_status' => ReportStatus::PENDING->value,
                'interview_status' => $interview->status->value,
            ], 202);
        }

        // Report generation is still in progress
        if (in_array($report->status, [
            ReportStatus::PENDING,
            ReportStatus::PROCESSING,
        ], true)) {
            return response()->json([
                'message' => 'Your report is being generated.',
                'report_status' => $report->status->value,
            ], 202);
        }

        // Pass the already-loaded interview to the resource
        $report->setRelation('interview', $interview);

        // Return the completed or failed report.
        return FinalReportResource::make($report)->response();
    }
}
