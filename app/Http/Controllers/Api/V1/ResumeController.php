<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreResumeRequest;
use App\Http\Resources\ResumeResource;
use App\Models\Resume;
use App\Services\StoreResumeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ResumeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return ResumeResource::collection(
            $request->user()->resumes()
            ->select(['id','title','parsing_status','created_at','updated_at'])
            ->latest('id')->paginate(15)
        );
    }

    public function store(StoreResumeRequest $request, StoreResumeService $service): JsonResponse
    {
        $resume = $service->execute(
            $request->user(),
            $request->file('file'),
            $request->validated('title')
        );

        $status = $resume->wasRecentlyCreated
            ? ($resume->parsing_status === Resume::FAILED ? 201 : 202)
            : 200;

        return response()->json([
            'message' => $resume->wasRecentlyCreated
                ? ($resume->parsing_status === Resume::FAILED
                    ? 'Resume uploaded, but processing could not be started.'
                    : 'Resume uploaded and queued for processing.')
                : 'This resume already exists. The existing resume is reused.',
            'reused' => ! $resume->wasRecentlyCreated,
            'data' => (new ResumeResource($resume))->resolve($request),
        ], $status);
    }

    public function show(Request $request, int $resume): ResumeResource
    {
        // Query through the owner so other users' resumes are never exposed.
        $record = $request->user()->resumes()->with('skills')->findOrFail($resume);

        return new ResumeResource($record);
    }
}
