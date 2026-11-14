<?php

namespace App\OpenApi\Endpoints;

use OpenApi\Spec as OA;

final class AiRecoveryStatusEndpoints
{
    #[OA\Operation\Get(
        path: '/api/v1/interviews/{interview}/questions',
        operationId: 'listInterviewQuestions',
        tags: ['Interviews'],
        summary: 'Read interview questions and answer evaluation states',
        description: 'Poll after interview preparation or answer evaluation retry. Preparation failures expose ai_failure at the top level with HTTP 409. Answer failures expose ai_failure inside each saved answer in the HTTP 200 question list. These are different response locations. Questions and answers are returned only for the owner.',
        parameters: [new OA\Parameter\Path(name: 'interview', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Questions in sequence order, with saved answers when present.', content: new OA\MediaType\Json(
                type: 'object', required: ['status', 'interview_status', 'data'], properties: [
                    'status' => new OA\Schema(type: 'string', example: 'success'),
                    'interview_status' => new OA\Schema(type: 'string', enum: ['ready', 'in_progress', 'completed']),
                    'data' => new OA\Schema(type: 'array', items: new OA\Schema(ref: '#/components/schemas/InterviewQuestion')),
                ],
            )),
            new OA\Response(response: 202, description: 'Questions are still being generated.', content: new OA\MediaType\Json(
                type: 'object', example: ['status' => 'processing', 'interview_status' => 'preparing', 'message' => 'Interview questions are being generated.', 'data' => null],
            )),
            new OA\Response(response: 401, description: 'Authentication required.', content: new OA\MediaType\Json(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 404, description: 'Owned interview not found.'),
            new OA\Response(response: 409, description: 'Interview preparation failed. Retry POST /interviews/{interview}/retry after addressing the cause.', content: new OA\MediaType\Json(
                type: 'object', required: ['status', 'interview_status', 'message', 'ai_failure'], properties: [
                    'status' => new OA\Schema(type: 'string', example: 'error'),
                    'interview_status' => new OA\Schema(type: 'string', example: 'failed'),
                    'message' => new OA\Schema(type: 'string'),
                    'ai_failure' => new OA\Schema(ref: '#/components/schemas/AiFailure'),
                ],
            )),
        ],
    )]
    public function questions(): void {}

    #[OA\Operation\Get(
        path: '/api/v1/job-descriptions/{job_description}/gap-analysis/{resume_id}',
        operationId: 'readOrCreateGapAnalysis',
        tags: ['Gap Analysis'],
        summary: 'Read or create gap analysis and inspect roadmap status',
        description: 'Both sources must belong to the user and have completed parsing. On first access, calculates and saves the local analysis and queues AI roadmap generation. Later reads return the saved analysis and do not retry a failed roadmap. Poll this path after POST /gap-analyses/{analysis}/roadmap/retry, using the source IDs rather than the analysis ID. Inspect data.roadmap_status and data.ai_failure. Other data fields retain the existing analysis and roadmap payloads.',
        parameters: [
            new OA\Parameter\Path(name: 'job_description', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter\Path(name: 'resume_id', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Existing analysis, including pending, processing, completed or failed roadmap state.', content: new OA\MediaType\Json(ref: '#/components/schemas/GapAnalysisStatusResponse')),
            new OA\Response(response: 201, description: 'Analysis first created. Roadmap processing may already have advanced or failed to queue.', content: new OA\MediaType\Json(ref: '#/components/schemas/GapAnalysisStatusResponse')),
            new OA\Response(response: 401, description: 'Authentication required.'),
            new OA\Response(response: 404, description: 'Owned source not found.'),
            new OA\Response(response: 422, description: 'Source parsing is not completed.', content: new OA\MediaType\Json(type: 'object', example: ['status' => 'error', 'message' => 'Analysis unavailable while processing resource data queues.'])),
        ],
    )]
    public function roadmap(): void {}

    #[OA\Operation\Get(
        path: '/api/v1/interviews/{interview}/report',
        operationId: 'readFinalReport',
        tags: ['Final Reports'],
        summary: 'Read final report or its current processing state',
        description: 'Owner only. Requires a completed interview with saved answers and no failed answer evaluations. A completed or failed report is returned with HTTP 200: inspect data.status and data.ai_failure. HTTP 202 has no data wrapper and can mean answer evaluations are pending, no report exists yet, or the report is pending/processing. This GET does not create or retry a missing report. Errors before report creation are not exposed as a failed report.',
        parameters: [new OA\Parameter\Path(name: 'interview', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Completed or failed report with existing questions, score and AI result fields.', content: new OA\MediaType\Json(
                type: 'object', required: ['data'], properties: ['data' => new OA\Schema(ref: '#/components/schemas/FinalReport')],
            )),
            new OA\Response(response: 202, description: 'Waiting for evaluations, report creation or report processing. report_status and interview_status appear only in the corresponding branch.', content: new OA\MediaType\Json(
                type: 'object', required: ['message'], properties: [
                    'message' => new OA\Schema(type: 'string'),
                    'report_status' => new OA\Schema(type: 'string', enum: ['pending', 'processing']),
                    'interview_status' => new OA\Schema(type: 'string', example: 'completed'),
                ], example: ['message' => 'Your report is being generated.', 'report_status' => 'processing'],
            )),
            new OA\Response(response: 401, description: 'Authentication required.'),
            new OA\Response(response: 404, description: 'Owned interview not found.'),
            new OA\Response(response: 409, description: 'Interview failed or is incomplete, a question has no answer, or an answer evaluation failed.', content: new OA\MediaType\Json(ref: '#/components/schemas/AiRetryError')),
        ],
    )]
    public function report(): void {}
}
