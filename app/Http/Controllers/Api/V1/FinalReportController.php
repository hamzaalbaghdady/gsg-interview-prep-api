<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InterviewAnswerStatus;
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

        // Check if all questions have been answered
        if ($interview->questions()->whereDoesntHave('answer')->exists()) {
            abort(409, 'Finish answering all questions first.');
        }

        // Check if all answers have been evaluated
        if ($interview->questions()
            ->whereHas('answer', function ($query) {
                $query->where(
                    'evaluation_status',
                    '!=',
                    InterviewAnswerStatus::COMPLETED->value
                );
            })
            ->exists()
        ) {
            abort(409, 'Some answers are waiting for evaluation.');
        }

        // Load the report, questions, and their answers
        $interview->load(['report', 'questions.answer',]);

        $report = $interview->report;

        // Report has not been created yet
        if ($report === null) {
            return response()->json([
                'message' => 'The report is not available yet.',
                'report_status' => ReportStatus::PENDING->value,
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

        // Report generation failed
        if ($report->status === ReportStatus::FAILED) { // Add handling here if any
            return response()->json([
                'message' => 'Report generation failed.',
                'report_status' => $report->status->value,
            ], 409);
        }

        // Pass the already-loaded interview to the resource
        $report->setRelation('interview', $interview);

        // Return the completed report.
        return FinalReportResource::make($report)->response();
    }
}
