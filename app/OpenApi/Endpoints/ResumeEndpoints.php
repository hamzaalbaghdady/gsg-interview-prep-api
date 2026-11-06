<?php

namespace App\OpenApi\Endpoints;

use OpenApi\Spec as OA;

final class ResumeEndpoints
{
    #[OA\Operation\Get(
        path: '/api/v1/resumes',
        operationId: 'listResumes',
        tags: ['Resumes'],
        summary: 'List your resumes',
        description: 'Returns only the authenticated user\'s resumes, newest ID first, with 15 records per page. Extracted text and skills are not included.',
        parameters: [
            new OA\Parameter\Query(name: 'page', description: 'Page number.', schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated resumes.',
                content: new OA\MediaType\Json(
                    type: 'object',
                    required: ['data', 'links', 'meta'],
                    properties: [
                        'data' => new OA\Schema(type: 'array', items: new OA\Schema(ref: '#/components/schemas/Resume')),
                        'links' => new OA\Schema(ref: '#/components/schemas/PaginationLinks'),
                        'meta' => new OA\Schema(ref: '#/components/schemas/PaginationMeta'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Missing, invalid, or revoked Bearer token.', content: new OA\MediaType\Json(ref: '#/components/schemas/UnauthenticatedError')),
        ],
    )]
    public function index(): void {}

    #[OA\Operation\Post(
        path: '/api/v1/resumes',
        operationId: 'uploadResume',
        tags: ['Resumes'],
        summary: 'Upload a resume',
        description: 'Upload a PDF or TXT file up to 5120 KB (5 MiB). File contents are deduplicated per user; uploading identical contents reuses the existing record without changing its title or restarting processing. New files are processed in the background. Poll GET /api/v1/resumes/{resume} until parsing_status is completed or failed. Processing requires a queue worker and a configured Python service. The upload response omits extracted_text and skills.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(ref: '#/components/schemas/ResumeUploadRequest'),
            ),
        ),
        responses: [
            new OA\Response(
                response: 202,
                description: 'New resume uploaded and queued for processing. reused is false and the initial parsing_status is pending. A queue worker changes it to processing when analysis starts; poll the detail endpoint for updates. message: "Resume uploaded and queued for processing."',
                content: new OA\MediaType\Json(ref: '#/components/schemas/ResumeUploadResponse'),
            ),
            new OA\Response(
                response: 200,
                description: 'Identical contents already exist for this user. reused is true; data contains the existing resume in its current state. message: "This resume already exists. The existing resume is reused."',
                content: new OA\MediaType\Json(
                    ref: '#/components/schemas/ResumeUploadResponse',
                    example: [
                        'message' => 'This resume already exists. The existing resume is reused.',
                        'reused' => true,
                        'data' => ['id' => 1, 'title' => 'Backend Developer CV', 'parsing_status' => 'completed', 'processing_error' => null, 'created_at' => '2026-10-30T12:00:00.000000Z', 'updated_at' => '2026-10-30T12:01:00.000000Z'],
                    ],
                ),
            ),
            new OA\Response(
                response: 201,
                description: 'New resume saved, but background processing could not be started. reused is false and parsing_status is failed.',
                content: new OA\MediaType\Json(
                    ref: '#/components/schemas/ResumeUploadResponse',
                    example: [
                        'message' => 'Resume uploaded, but processing could not be started.',
                        'reused' => false,
                        'data' => ['id' => 1, 'title' => 'Backend Developer CV', 'parsing_status' => 'failed', 'processing_error' => 'Resume processing failed. Please try again later.', 'created_at' => '2026-10-30T12:00:00.000000Z', 'updated_at' => '2026-10-30T12:00:00.000000Z'],
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Missing, invalid, or revoked Bearer token.', content: new OA\MediaType\Json(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(
                response: 422,
                description: 'Missing or invalid file, file larger than 5120 KB, or invalid title.',
                content: new OA\MediaType\Json(
                    ref: '#/components/schemas/ValidationError',
                    example: ['message' => 'The file field is required.', 'errors' => ['file' => ['The file field is required.']]],
                ),
            ),
        ],
    )]
    public function store(): void {}

    #[OA\Operation\Get(
        path: '/api/v1/resumes/{resume}',
        operationId: 'getResume',
        tags: ['Resumes'],
        summary: 'Get a resume and its extracted skills',
        description: 'Returns a resume owned by the authenticated user, including extracted_text and skills. Use this endpoint to poll processing status. A failed processing job is represented by parsing_status=failed in a successful HTTP 200 response.',
        parameters: [
            new OA\Parameter\Path(name: 'resume', description: 'Resume ID.', schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Resume details.',
                content: new OA\MediaType\Json(
                    type: 'object',
                    required: ['data'],
                    properties: ['data' => new OA\Schema(ref: '#/components/schemas/ResumeDetail')],
                    example: [
                        'data' => [
                            'id' => 1,
                            'title' => 'Backend Developer CV',
                            'parsing_status' => 'completed',
                            'processing_error' => null,
                            'created_at' => '2026-10-30T12:00:00.000000Z',
                            'updated_at' => '2026-10-30T12:01:00.000000Z',
                            'extracted_text' => 'Backend developer experienced in Laravel and MySQL.',
                            'skills' => [['id' => 1, 'name' => 'Laravel', 'category' => 'technical', 'confidence_score' => 0.95]],
                        ],
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Missing, invalid, or revoked Bearer token.', content: new OA\MediaType\Json(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(
                response: 404,
                description: 'Resume does not exist or belongs to another user. Non-numeric IDs do not match the route.',
                content: new OA\MediaType\Json(
                    type: 'object',
                    required: ['message'],
                    properties: ['message' => new OA\Schema(type: 'string', description: 'Example for a missing numeric ID. Wording may vary with route matching and debug settings.', example: 'No query results for model [App\\Models\\Resume] 999')],
                ),
            ),
        ],
    )]
    public function show(): void {}
}
