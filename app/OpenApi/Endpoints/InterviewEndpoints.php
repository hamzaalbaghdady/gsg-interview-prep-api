<?php

namespace App\OpenApi\Endpoints;

use App\Models\Interview;
use OpenApi\Spec as OA;

final class InterviewEndpoints
{
    #[OA\Operation\Post(
        path: '/api/v1/interviews',
        operationId: 'createInterview',
        tags: ['Interviews'],
        summary: 'Create an interview session',
        description: 'Creates an interview session in the preparing state for an owned resume and job description. Both sources must be processed and contain text, and the exact pair must have a saved gap analysis.',
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType\Json(ref: '#/components/schemas/InterviewRequest')),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns the open interview for the same source pair.',
                content: new OA\MediaType\Json(
                    type: 'object',
                    required: ['data'],
                    properties: ['data' => new OA\Schema(ref: '#/components/schemas/Interview')],
                ),
            ),
            new OA\Response(
                response: 201,
                description: 'Interview session created in the preparing state.',
                content: new OA\MediaType\Json(
                    type: 'object',
                    required: ['data'],
                    properties: ['data' => new OA\Schema(ref: '#/components/schemas/Interview')],
                    example: ['data' => [
                        'id' => 42,
                        'resume_id' => 12,
                        'job_description_id' => 34,
                        'status' => 'preparing',
                        'total_questions' => Interview::INITIAL_QUESTION_COUNT,
                        'started_at' => null,
                        'completed_at' => null,
                    ]],
                ),
            ),
            new OA\Response(response: 401, description: 'Missing, invalid, or revoked Bearer token.', content: new OA\MediaType\Json(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(
                response: 404,
                description: 'The resume or job description was not found among the authenticated user\'s resources.',
                content: new OA\MediaType\Json(
                    type: 'object',
                    required: ['message'],
                    properties: ['message' => new OA\Schema(type: 'string', example: 'No query results for model [App\\Models\\Resume] 999')],
                ),
            ),
            new OA\Response(
                response: 409,
                description: 'The user has an open interview for a different source pair.',
                content: new OA\MediaType\Json(
                    type: 'object',
                    required: ['message', 'interview_id'],
                    properties: [
                        'message' => new OA\Schema(type: 'string', example: 'Finish your open interview before starting another one.'),
                        'interview_id' => new OA\Schema(type: 'integer', example: 42),
                    ],
                ),
            ),
            new OA\Response(
                response: 422,
                description: 'Invalid identifiers, unprocessed or empty source text, or no saved gap analysis for the pair.',
                content: new OA\MediaType\Json(
                    ref: '#/components/schemas/ValidationError',
                    example: [
                        'message' => 'Run a gap analysis for this resume and job description before creating an interview.',
                        'errors' => ['gap_analysis' => ['Run a gap analysis for this resume and job description before creating an interview.']],
                    ],
                ),
            ),
        ],
    )]
    public function store(): void {}
}
