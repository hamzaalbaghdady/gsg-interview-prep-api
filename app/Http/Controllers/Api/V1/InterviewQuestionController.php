<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InterviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\InterviewQuestionResource;
use App\Models\Interview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InterviewQuestionController extends Controller
{
    /**
     * Display a complete chronological list of questions generated for this interview.
     */
    public function index(Request $request, Interview $interview): JsonResponse
    {
        // check if user owns the interview
        $this->ensureInterviewOwnership($request, $interview);

        // check interview status
        if ($response = $this->getInterviewAvailabilityResponse($interview)) {
            return $response;
        }

        // Fetch all generated questions sequentially, eager-loading with answers
        $questions = $interview->questions()->with('answer')->get(); // needs answer relation to work.

        return response()->json([
            'status' => 'success',
            'interview_status' => $interview->status,
            'data' => InterviewQuestionResource::collection($questions)
        ]);
    }


    /**
     * fetching the single next unanswered question in the queue.
     */
    public function next(Request $request, Interview $interview): JsonResponse
    {
        // check if user owns the interview
        $this->ensureInterviewOwnership($request, $interview);

        // check interview status
        if ($response = $this->getInterviewAvailabilityResponse($interview)) {
            return $response;
        }

        // if interview completed
        if ($interview->status === InterviewStatus::COMPLETED) {
            return response()->json([
                'status' => 'success',
                'message' => 'Interview completed.',
                'interview_status' => $interview->status,
                'data' => null,
            ]);
        }

        // find the first question that doesnt have a matching answer yet
        $nextQuestion = $interview->questions()
            ->whereDoesntHave('answer')
            ->orderBy('sequence_number', 'asc')
            ->first();

        // If no unanswered question remains, the interview phase is finished
        if ($nextQuestion === null) {
            // todo: make the follow-up questions here
            return response()->json([
                'status' => 'success',
                'interview_status' => $interview->status,
                'message' => 'All generated questions have been answered.',
                'data' => null
            ]);
        }

        return response()->json([
            'status' => 'success',
            'interview_status' => $interview->status,
            'data' => new InterviewQuestionResource($nextQuestion)
        ]);
    }

    /**
     * Ensure the authenticated user owns the interview.
     */
    private function ensureInterviewOwnership(Request $request, Interview $interview): void
    {
        abort_unless(
            $request->user()->id === $interview->user_id,
            404,
            'Resource not found.'
        );
    }

    /**
     * Return a response if the interview is not ready for question retrieval.
     */
    private function getInterviewAvailabilityResponse(Interview $interview): ?JsonResponse
    {
        return match ($interview->status) {
            InterviewStatus::PREPARING => response()->json([
                'status' => 'processing',
                'interview_status' => $interview->status,
                'message' => 'Interview questions are being generated.',
                'data' => null,
            ], 202),

            InterviewStatus::FAILED => response()->json([
                'status' => 'error',
                'interview_status' => $interview->status,
                'message' => 'Interview preparation failed. Please try again.',
            ], 409),

            default => null,
        };
    }
}
