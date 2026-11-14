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
                        'ai_failure' => null,
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

    #[OA\Operation\Post(
        path: '/api/v1/interviews/{interview}/questions/{question}/answer',
        operationId: 'submitInterviewAnswer',
        tags: ['Interviews'],
        summary: 'Submit an interview answer',
        description: 'Saves one answer for a question in an owned interview and queues its evaluation after saving. The client can show the next question as soon as the answer is saved. The interview becomes completed when all answers are saved, even if evaluations are still running. A failed evaluation exposes ai_failure; use the answer retry endpoint instead of resubmitting the same answer.',
        parameters: [
            new OA\Parameter\Path(name: 'interview', description: 'Interview ID.', schema: new OA\Schema(type: 'integer'), example: 15),
            new OA\Parameter\Path(name: 'question', description: 'Question ID from the interview question list.', schema: new OA\Schema(type: 'integer'), example: 42),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType\Json(
                type: 'object',
                required: ['answer'],
                properties: [
                    'answer' => new OA\Schema(type: 'string', minLength: 1, maxLength: 10000, example: 'I built REST APIs with FastAPI.'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'The same answer was already saved. Returns the existing answer ID and its current evaluation status without queuing another evaluation.',
                content: new OA\MediaType\Json(
                    ref: '#/components/schemas/InterviewAnswerSubmission',
                    example: ['data' => ['answer_id' => 101, 'evaluation_status' => 'completed', 'ai_failure' => null]],
                ),
            ),
            new OA\Response(
                response: 201,
                description: 'Answer saved. Evaluation normally starts in the pending state. If queue dispatch fails, the saved answer is returned with a failed evaluation status.',
                content: new OA\MediaType\Json(
                    ref: '#/components/schemas/InterviewAnswerSubmission',
                    example: ['data' => ['answer_id' => 101, 'evaluation_status' => 'pending', 'ai_failure' => null]],
                ),
            ),
            new OA\Response(response: 401, description: 'Missing, invalid, or revoked Bearer token.', content: new OA\MediaType\Json(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 404, description: 'The interview was not found for the authenticated user, or the question does not belong to the interview.'),
            new OA\Response(
                response: 409,
                description: 'The question already has a different answer, or the interview is not accepting new answers.',
                content: new OA\MediaType\Json(
                    type: 'object',
                    required: ['message'],
                    properties: ['message' => new OA\Schema(type: 'string', example: 'This question already has an answer.')],
                ),
            ),
            new OA\Response(
                response: 422,
                description: 'The answer must be a non-empty string of at most 10,000 characters.',
                content: new OA\MediaType\Json(
                    ref: '#/components/schemas/ValidationError',
                    example: ['message' => 'The answer field is required.', 'errors' => ['answer' => ['The answer field is required.']]],
                ),
            ),
        ],
    )]
    public function submitAnswer(): void {}
}
