<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InterviewStatus;
use App\Enums\ParsingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreInterviewRequest;
use App\Http\Resources\Api\V1\InterviewResource;
use App\Jobs\ProcessInterviewQuestion;
use App\Models\GapAnalysis;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InterviewController extends Controller
{
    public function store(StoreInterviewRequest $request): JsonResponse
    {
        $user = $request->user();
        $resume = $user->resumes()->findOrFail(
            $request->validated('resume_id'),
            ['id', 'parsing_status', 'extracted_text'],
        );
        $jobDescription = $user->jobDescriptions()->findOrFail(
            $request->validated('job_description_id'),
            ['id', 'parsing_status', 'description'],
        );

        if ($resume->parsing_status !== Resume::COMPLETED || blank($resume->extracted_text)) {
            throw ValidationException::withMessages([
                'resume_id' => 'The resume must be processed and contain extracted text.',
            ]);
        }

        if ($jobDescription->parsing_status !== ParsingStatus::COMPLETED || blank($jobDescription->description)) {
            throw ValidationException::withMessages([
                'job_description_id' => 'The job description must be processed and contain text.',
            ]);
        }

        if (! GapAnalysis::query()->forPair($resume->id, $jobDescription->id)->exists()) {
            throw ValidationException::withMessages([
                'gap_analysis' => 'Run a gap analysis for this resume and job description before creating an interview.',
            ]);
        }

        $interview = DB::transaction(function () use ($user, $resume, $jobDescription) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id, ['id']);

            $openInterview = $lockedUser->interviews()
                ->whereIn('status', [
                    InterviewStatus::PREPARING->value,
                    InterviewStatus::READY->value,
                    InterviewStatus::IN_PROGRESS->value,
                ])
                ->lockForUpdate()
                ->first();

            return $openInterview ?? $lockedUser->interviews()->create([
                'resume_id' => $resume->id,
                'job_description_id' => $jobDescription->id,
            ]);
        });

        if ($interview->resume_id !== $resume->id || $interview->job_description_id !== $jobDescription->id) {
            return response()->json([
                'message' => 'Finish your open interview before starting another one.',
                'interview_id' => $interview->id,
            ], 409);
        }

        // Trigger questions generation job
        if ($interview->wasRecentlyCreated) {
            ProcessInterviewQuestion::dispatch($interview->id)->afterCommit();
        }
        return (new InterviewResource($interview))->response()
            ->setStatusCode($interview->wasRecentlyCreated ? 201 : 200);
    }
}
