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
        if ($request->user()->id !== $interview->user_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Resource not found.'
            ], 404);
        }

        // Fetch all generated questions sequentially, eager-loading with answers
        $questions = $interview->questions()->with('answer')->get(); // needs answer relation to work.

        return response()->json([
            'status' => 'success',
            'data' => InterviewQuestionResource::collection($questions)
        ]);
    }

    /**
     * fetching the single next unanswered question in the queue.
     */
    public function next(Request $request, Interview $interview): JsonResponse
    {
        // check if user owns the interview
        if ($request->user()->id !== $interview->user_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Resource not found.'
            ], 404);
        }

        // if the questions isn't generated yet
        if ($interview->status === InterviewStatus::PREPARING) {
            return response()->json([
                'status' => 'processing',
                'message' => 'Interview questions are being generated.',
                'data' => null,
            ], 202);
        }

        // if interview failed
        if ($interview->status === InterviewStatus::FAILED) {
            return response()->json([
                'status' => 'error',
                'message' => 'Interview preparation failed. Please try again.',
            ], 409);
        }

        // if interview completed
        if ($interview->status === InterviewStatus::COMPLETED) {
            return response()->json([
                'status' => 'success',
                'message' => 'Interview completed.',
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
                'message' => 'All generated questions have been answered.',
                'data' => null
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => new InterviewQuestionResource($nextQuestion)
        ]);
    }
}
