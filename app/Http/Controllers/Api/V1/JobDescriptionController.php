<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreJobDescriptionRequest;
use App\Models\JobDescription;
use Illuminate\Http\Request;

class JobDescriptionController extends Controller
{

    /**
     * Display the all user resources.
     */
    public function index(Request $request)
    {
        // Return latest 15 job description without the description part.
        $data = $request->user()
            ->jobDescriptions()
            ->select(['id', 'user_id', 'title', 'company_name', 'created_at'])
            ->latest()
            ->paginate($request->query('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreJobDescriptionRequest $request)
    {
        // Validate request
        $validated = $request->validated();

        // Store data
        $user = $request->user();
        $jobDescription = $user->jobDescriptions()->create($validated);

        // Return response
        return response()->json([
            'status' => 'success',
            'message' => 'Job Description created successfully.',
            'data' => $jobDescription
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, JobDescription $jobDescription)
    {
        // Check if user owns this resource
        if ($request->user()->id !== $jobDescription->user_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Resource not found.' // 404 is better for security here
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $jobDescription
        ]);
    }
}
