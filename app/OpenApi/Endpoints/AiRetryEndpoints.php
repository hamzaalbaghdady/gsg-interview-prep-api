<?php

namespace App\OpenApi\Endpoints;

use OpenApi\Spec as OA;

final class AiRetryEndpoints
{
    #[OA\Operation\Post(
        path: '/api/v1/resumes/{resume}/retry',
        operationId: 'retryAiResume',
        tags: ['AI Recovery'],
        summary: 'Retry resume parsing',
        description: 'Retry parsing on the existing failed resume. Restoring a missing upload may require support. Requires a Sanctum Bearer token. Send an empty body with Accept: application/json. Every stored failure code permits manual retry, but the cause may need to be fixed first. Resets the same record, clears its previous failure, and starts a new run. A repeated request after restart returns 409. All six retry endpoints share a limit of 10 requests per minute per authenticated user. Accepted means queued, not completed; the returned status is a refreshed snapshot and a fast worker may already have advanced it. Poll: GET /api/v1/resumes/{resume}; inspect data.parsing_status and data.ai_failure.',
        parameters: [
            new OA\Parameter\Path(name: 'resume', description: 'Resume database ID.', schema: new OA\Schema(type: 'integer'), example: 42),
        ],
        responses: [
            new OA\Response(response: 202, description: 'Restart accepted on the existing record; poll for completion.', content: new OA\MediaType\Json(
                ref: '#/components/schemas/AiRetryResponse',
                example: ['message' => 'Processing has been queued again.', 'data' => ['id' => 42, 'status' => 'pending', 'ai_failure' => null]],
            )),
            new OA\Response(response: 401, description: 'Missing, invalid or revoked Bearer token.', content: new OA\MediaType\Json(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 404, description: 'Owned resume not found.', content: new OA\MediaType\Json(ref: '#/components/schemas/AiRetryError')),
            new OA\Response(response: 409, description: 'Only failed operations can be retried.', content: new OA\MediaType\Json(
                ref: '#/components/schemas/AiRetryError', example: ['message' => 'Only failed operations can be retried.'],
            )),
            new OA\Response(response: 429, description: 'The shared retry throttle was exceeded. This does not describe a Python service error.',
                headers: [new OA\Header(header: 'Retry-After', description: 'Seconds until another retry request is allowed.', schema: new OA\Schema(type: 'integer', minimum: 0), example: 30)],
                content: new OA\MediaType\Json(ref: '#/components/schemas/AiRetryError', example: ['message' => 'Too Many Attempts.']),
            ),
            new OA\Response(response: 503, description: 'Restart ended in a failed state. Queue dispatch failure normally stores queue_unavailable; inspect data.ai_failure.', content: new OA\MediaType\Json(
                ref: '#/components/schemas/AiRetryResponse',
                example: ['message' => 'Processing could not be restarted.', 'data' => ['id' => 42, 'status' => 'failed', 'ai_failure' => [
                    'error_code' => 'queue_unavailable', 'error_message' => 'Processing could not be queued. Please try again later.', 'can_retry' => true,
                ]]],
            )),
        ],
    )]
    public function resume(): void {}

    #[OA\Operation\Post(
        path: '/api/v1/job-descriptions/{jobDescription}/retry',
        operationId: 'retryAiJobDescription',
        tags: ['AI Recovery'],
        summary: 'Retry job description parsing',
        description: 'Retry skill extraction on the existing failed job description. Requires a Sanctum Bearer token. Send an empty body with Accept: application/json. Every stored failure code permits manual retry, but the cause may need to be fixed first. Resets the same record, clears its previous failure, and starts a new run. A repeated request after restart returns 409. All six retry endpoints share a limit of 10 requests per minute per authenticated user. Accepted means queued, not completed; the returned status is a refreshed snapshot and a fast worker may already have advanced it. Poll: GET /api/v1/job-descriptions/{jobDescription}; inspect data.parsing_status and data.ai_failure.',
        parameters: [
            new OA\Parameter\Path(name: 'jobDescription', description: 'Job description database ID.', schema: new OA\Schema(type: 'integer'), example: 42),
        ],
        responses: [
            new OA\Response(response: 202, description: 'Restart accepted on the existing record; poll for completion.', content: new OA\MediaType\Json(
                ref: '#/components/schemas/AiRetryResponse',
                example: ['message' => 'Processing has been queued again.', 'data' => ['id' => 42, 'status' => 'pending', 'ai_failure' => null]],
            )),
            new OA\Response(response: 401, description: 'Missing, invalid or revoked Bearer token.', content: new OA\MediaType\Json(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 404, description: 'Owned job description not found.', content: new OA\MediaType\Json(ref: '#/components/schemas/AiRetryError')),
            new OA\Response(response: 409, description: 'Only failed operations can be retried.', content: new OA\MediaType\Json(
                ref: '#/components/schemas/AiRetryError', example: ['message' => 'Only failed operations can be retried.'],
            )),
            new OA\Response(response: 429, description: 'The shared retry throttle was exceeded. This does not describe a Python service error.',
                headers: [new OA\Header(header: 'Retry-After', description: 'Seconds until another retry request is allowed.', schema: new OA\Schema(type: 'integer', minimum: 0), example: 30)],
                content: new OA\MediaType\Json(ref: '#/components/schemas/AiRetryError', example: ['message' => 'Too Many Attempts.']),
            ),
            new OA\Response(response: 503, description: 'Restart ended in a failed state. Queue dispatch failure normally stores queue_unavailable; inspect data.ai_failure.', content: new OA\MediaType\Json(
                ref: '#/components/schemas/AiRetryResponse',
                example: ['message' => 'Processing could not be restarted.', 'data' => ['id' => 42, 'status' => 'failed', 'ai_failure' => [
                    'error_code' => 'queue_unavailable', 'error_message' => 'Processing could not be queued. Please try again later.', 'can_retry' => true,
                ]]],
            )),
        ],
    )]
    public function jobDescription(): void {}

    #[OA\Operation\Post(
        path: '/api/v1/gap-analyses/{analysis}/roadmap/retry',
        operationId: 'retryAiRoadmap',
        tags: ['AI Recovery'],
        summary: 'Retry roadmap generation',
        description: 'Retry the AI roadmap on the existing gap analysis. Both its resume and job description must belong to the authenticated user. The local gap calculation is not rerun. Requires a Sanctum Bearer token. Send an empty body with Accept: application/json. Every stored failure code permits manual retry, but the cause may need to be fixed first. Resets the same record, clears its previous failure, and starts a new run. A repeated request after restart returns 409. All six retry endpoints share a limit of 10 requests per minute per authenticated user. Accepted means queued, not completed; the returned status is a refreshed snapshot and a fast worker may already have advanced it. Poll: GET /api/v1/job-descriptions/{job_description}/gap-analysis/{resume_id}; inspect data.roadmap_status and data.ai_failure.',
        parameters: [
            new OA\Parameter\Path(name: 'analysis', description: 'Gap analysis database ID returned as data.id by the gap analysis endpoint.', schema: new OA\Schema(type: 'integer'), example: 42),
        ],
        responses: [
            new OA\Response(response: 202, description: 'Restart accepted on the existing record; poll for completion.', content: new OA\MediaType\Json(
                ref: '#/components/schemas/AiRetryResponse',
                example: ['message' => 'Processing has been queued again.', 'data' => ['id' => 42, 'status' => 'pending', 'ai_failure' => null]],
            )),
            new OA\Response(response: 401, description: 'Missing, invalid or revoked Bearer token.', content: new OA\MediaType\Json(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 404, description: 'Gap analysis not found, or either source belongs to another user.', content: new OA\MediaType\Json(ref: '#/components/schemas/AiRetryError')),
            new OA\Response(response: 409, description: 'Only failed operations can be retried.', content: new OA\MediaType\Json(
                ref: '#/components/schemas/AiRetryError', example: ['message' => 'Only failed operations can be retried.'],
            )),
            new OA\Response(response: 429, description: 'The shared retry throttle was exceeded. This does not describe a Python service error.',
                headers: [new OA\Header(header: 'Retry-After', description: 'Seconds until another retry request is allowed.', schema: new OA\Schema(type: 'integer', minimum: 0), example: 30)],
                content: new OA\MediaType\Json(ref: '#/components/schemas/AiRetryError', example: ['message' => 'Too Many Attempts.']),
            ),
            new OA\Response(response: 503, description: 'Restart ended in a failed state. Queue dispatch failure normally stores queue_unavailable; inspect data.ai_failure.', content: new OA\MediaType\Json(
                ref: '#/components/schemas/AiRetryResponse',
                example: ['message' => 'Processing could not be restarted.', 'data' => ['id' => 42, 'status' => 'failed', 'ai_failure' => [
                    'error_code' => 'queue_unavailable', 'error_message' => 'Processing could not be queued. Please try again later.', 'can_retry' => true,
                ]]],
            )),
        ],
    )]
    public function roadmap(): void {}

    #[OA\Operation\Post(
        path: '/api/v1/interviews/{interview}/retry',
        operationId: 'retryAiInterview',
        tags: ['AI Recovery'],
        summary: 'Retry interview preparation',
        description: 'Retry question generation for an existing failed interview. No questions may already exist, and the owner must have no other preparing, ready or in_progress interview. Requires a Sanctum Bearer token. Send an empty body with Accept: application/json. Every stored failure code permits manual retry, but the cause may need to be fixed first. Resets the same record, clears its previous failure, and starts a new run. A repeated request after restart returns 409. All six retry endpoints share a limit of 10 requests per minute per authenticated user. Accepted means queued, not completed; the returned status is a refreshed snapshot and a fast worker may already have advanced it. Poll: GET /api/v1/interviews/{interview}/questions; 202 means preparing, 409 with ai_failure means preparation failed, and 200 returns questions.',
        parameters: [
            new OA\Parameter\Path(name: 'interview', description: 'Interview database ID.', schema: new OA\Schema(type: 'integer'), example: 42),
        ],
        responses: [
            new OA\Response(response: 202, description: 'Restart accepted on the existing record; poll for completion.', content: new OA\MediaType\Json(
                ref: '#/components/schemas/AiRetryResponse',
                example: ['message' => 'Processing has been queued again.', 'data' => ['id' => 42, 'status' => 'preparing', 'ai_failure' => null]],
            )),
            new OA\Response(response: 401, description: 'Missing, invalid or revoked Bearer token.', content: new OA\MediaType\Json(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 404, description: 'Owned interview not found.', content: new OA\MediaType\Json(ref: '#/components/schemas/AiRetryError')),
            new OA\Response(response: 409, description: 'Only failed operations can be retried. Also rejected if another interview is open or questions already exist.', content: new OA\MediaType\Json(
                ref: '#/components/schemas/AiRetryError', example: ['message' => 'Only failed operations can be retried.'],
            )),
            new OA\Response(response: 429, description: 'The shared retry throttle was exceeded. This does not describe a Python service error.',
                headers: [new OA\Header(header: 'Retry-After', description: 'Seconds until another retry request is allowed.', schema: new OA\Schema(type: 'integer', minimum: 0), example: 30)],
                content: new OA\MediaType\Json(ref: '#/components/schemas/AiRetryError', example: ['message' => 'Too Many Attempts.']),
            ),
            new OA\Response(response: 503, description: 'Restart ended in a failed state. Queue dispatch failure normally stores queue_unavailable; inspect data.ai_failure.', content: new OA\MediaType\Json(
                ref: '#/components/schemas/AiRetryResponse',
                example: ['message' => 'Processing could not be restarted.', 'data' => ['id' => 42, 'status' => 'failed', 'ai_failure' => [
                    'error_code' => 'queue_unavailable', 'error_message' => 'Processing could not be queued. Please try again later.', 'can_retry' => true,
                ]]],
            )),
        ],
    )]
    public function interview(): void {}

    #[OA\Operation\Post(
        path: '/api/v1/interviews/{interview}/questions/{question}/answer/retry',
        operationId: 'retryAiAnswer',
        tags: ['AI Recovery'],
        summary: 'Retry answer evaluation',
        description: 'Retry evaluation of the saved answer without changing its text. The interview must be owned and the question and saved answer must exist. Requires a Sanctum Bearer token. Send an empty body with Accept: application/json. Every stored failure code permits manual retry, but the cause may need to be fixed first. Resets the same record, clears its previous failure, and starts a new run. A repeated request after restart returns 409. All six retry endpoints share a limit of 10 requests per minute per authenticated user. Accepted means queued, not completed; the returned status is a refreshed snapshot and a fast worker may already have advanced it. Poll: GET /api/v1/interviews/{interview}/questions; inspect data[].answer.evaluation_status and data[].answer.ai_failure. The response data.id below is the answer ID.',
        parameters: [
            new OA\Parameter\Path(name: 'interview', description: 'Interview database ID.', schema: new OA\Schema(type: 'integer'), example: 42),
            new OA\Parameter\Path(name: 'question', description: 'Question database ID from the question list, not sequence_number or answer_id.', schema: new OA\Schema(type: 'integer'), example: 42),
        ],
        responses: [
            new OA\Response(response: 202, description: 'Restart accepted on the existing record; poll for completion.', content: new OA\MediaType\Json(
                ref: '#/components/schemas/AiRetryResponse',
                example: ['message' => 'Processing has been queued again.', 'data' => ['id' => 42, 'status' => 'pending', 'ai_failure' => null]],
            )),
            new OA\Response(response: 401, description: 'Missing, invalid or revoked Bearer token.', content: new OA\MediaType\Json(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 404, description: 'Owned interview, its question or the saved answer not found.', content: new OA\MediaType\Json(ref: '#/components/schemas/AiRetryError')),
            new OA\Response(response: 409, description: 'Only failed operations can be retried.', content: new OA\MediaType\Json(
                ref: '#/components/schemas/AiRetryError', example: ['message' => 'Only failed operations can be retried.'],
            )),
            new OA\Response(response: 429, description: 'The shared retry throttle was exceeded. This does not describe a Python service error.',
                headers: [new OA\Header(header: 'Retry-After', description: 'Seconds until another retry request is allowed.', schema: new OA\Schema(type: 'integer', minimum: 0), example: 30)],
                content: new OA\MediaType\Json(ref: '#/components/schemas/AiRetryError', example: ['message' => 'Too Many Attempts.']),
            ),
            new OA\Response(response: 503, description: 'Restart ended in a failed state. Queue dispatch failure normally stores queue_unavailable; inspect data.ai_failure.', content: new OA\MediaType\Json(
                ref: '#/components/schemas/AiRetryResponse',
                example: ['message' => 'Processing could not be restarted.', 'data' => ['id' => 42, 'status' => 'failed', 'ai_failure' => [
                    'error_code' => 'queue_unavailable', 'error_message' => 'Processing could not be queued. Please try again later.', 'can_retry' => true,
                ]]],
            )),
        ],
    )]
    public function answer(): void {}

    #[OA\Operation\Post(
        path: '/api/v1/interviews/{interview}/report/retry',
        operationId: 'retryAiReport',
        tags: ['AI Recovery'],
        summary: 'Retry final report generation',
        description: 'Retry AI generation on the existing failed report. Does not create a missing report, recalculate overall_score or change saved answers. First-time creation remains in the existing report preparation flow. Requires a Sanctum Bearer token. Send an empty body with Accept: application/json. Every stored failure code permits manual retry, but the cause may need to be fixed first. Resets the same record, clears its previous failure, and starts a new run. A repeated request after restart returns 409. All six retry endpoints share a limit of 10 requests per minute per authenticated user. Accepted means queued, not completed; the returned status is a refreshed snapshot and a fast worker may already have advanced it. Poll: GET /api/v1/interviews/{interview}/report; 202 means waiting, while 200 contains data.status and data.ai_failure. A failed report is returned with HTTP 200 and data.status=failed. The retry response data.id is the report ID.',
        parameters: [
            new OA\Parameter\Path(name: 'interview', description: 'Interview database ID, not the report ID.', schema: new OA\Schema(type: 'integer'), example: 42),
        ],
        responses: [
            new OA\Response(response: 202, description: 'Restart accepted on the existing record; poll for completion.', content: new OA\MediaType\Json(
                ref: '#/components/schemas/AiRetryResponse',
                example: ['message' => 'Processing has been queued again.', 'data' => ['id' => 42, 'status' => 'pending', 'ai_failure' => null]],
            )),
            new OA\Response(response: 401, description: 'Missing, invalid or revoked Bearer token.', content: new OA\MediaType\Json(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 404, description: 'Owned interview or its existing report not found. A missing report is not created by retry.', content: new OA\MediaType\Json(ref: '#/components/schemas/AiRetryError')),
            new OA\Response(response: 409, description: 'Only failed operations can be retried.', content: new OA\MediaType\Json(
                ref: '#/components/schemas/AiRetryError', example: ['message' => 'Only failed operations can be retried.'],
            )),
            new OA\Response(response: 429, description: 'The shared retry throttle was exceeded. This does not describe a Python service error.',
                headers: [new OA\Header(header: 'Retry-After', description: 'Seconds until another retry request is allowed.', schema: new OA\Schema(type: 'integer', minimum: 0), example: 30)],
                content: new OA\MediaType\Json(ref: '#/components/schemas/AiRetryError', example: ['message' => 'Too Many Attempts.']),
            ),
            new OA\Response(response: 503, description: 'Restart ended in a failed state. Queue dispatch failure normally stores queue_unavailable; inspect data.ai_failure.', content: new OA\MediaType\Json(
                ref: '#/components/schemas/AiRetryResponse',
                example: ['message' => 'Processing could not be restarted.', 'data' => ['id' => 42, 'status' => 'failed', 'ai_failure' => [
                    'error_code' => 'queue_unavailable', 'error_message' => 'Processing could not be queued. Please try again later.', 'can_retry' => true,
                ]]],
            )),
        ],
    )]
    public function report(): void {}
}
