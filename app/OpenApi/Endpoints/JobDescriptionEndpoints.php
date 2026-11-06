<?php

namespace App\OpenApi\Endpoints;

use OpenApi\Spec as OA;

final class JobDescriptionEndpoints
{
    #[OA\Operation\Get(
        path: '/api/v1/job-descriptions',
        operationId: 'listJobDescriptions',
        tags: ['Job Descriptions'],
        summary: 'List your job descriptions',
        description: 'Returns only the authenticated user\'s job descriptions, newest created_at first. Description text and skills are omitted. The current list query returns updated_at as null.',
        parameters: [
            new OA\Parameter\Query(name: 'page', description: 'Page number.', schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)),
            new OA\Parameter\Query(name: 'per_page', description: 'Records per page. Defaults to 15; the current endpoint does not enforce an upper limit.', schema: new OA\Schema(type: 'integer', minimum: 1, default: 15)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated job descriptions.',
                content: new OA\MediaType\Json(
                    type: 'object',
                    required: ['data', 'links', 'meta'],
                    properties: [
                        'data' => new OA\Schema(type: 'array', items: new OA\Schema(ref: '#/components/schemas/JobDescription')),
                        'links' => new OA\Schema(ref: '#/components/schemas/PaginationLinks'),
                        'meta' => new OA\Schema(ref: '#/components/schemas/PaginationMeta'),
                    ],
                    example: [
                        'data' => [['id' => 1, 'title' => 'Laravel Backend Developer', 'company_name' => 'Example Company', 'parsing_status' => 'pending', 'created_at' => '2026-10-30T12:00:00.000000Z', 'updated_at' => null]],
                        'links' => ['first' => 'http://localhost:8000/api/v1/job-descriptions?page=1', 'last' => 'http://localhost:8000/api/v1/job-descriptions?page=1', 'prev' => null, 'next' => null],
                        'meta' => ['current_page' => 1, 'from' => 1, 'last_page' => 1, 'links' => [['url' => null, 'label' => '&laquo; Previous', 'active' => false], ['url' => 'http://localhost:8000/api/v1/job-descriptions?page=1', 'label' => '1', 'active' => true], ['url' => null, 'label' => 'Next &raquo;', 'active' => false]], 'path' => 'http://localhost:8000/api/v1/job-descriptions', 'per_page' => 15, 'to' => 1, 'total' => 1],
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Missing, invalid, or revoked Bearer token.', content: new OA\MediaType\Json(ref: '#/components/schemas/UnauthenticatedError')),
        ],
    )]
    public function index(): void {}

    #[OA\Operation\Post(
        path: '/api/v1/job-descriptions',
        operationId: 'createJobDescription',
        tags: ['Job Descriptions'],
        summary: 'Create a job description',
        description: 'Saves the job description and queues AI skill extraction. The initial state is pending; since the record is refreshed before responding, processing may already have advanced. Poll the detail endpoint until parsing_status is completed or failed. Extraction requires a queue worker and the configured Python service. The creation response omits description and skills.',
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType\Json(ref: '#/components/schemas/JobDescriptionRequest')),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Job description created and extraction queued.',
                content: new OA\MediaType\Json(
                    type: 'object',
                    required: ['status', 'message', 'data'],
                    properties: [
                        'status' => new OA\Schema(type: 'string', example: 'success'),
                        'message' => new OA\Schema(type: 'string', example: 'Job Description created successfully and queued for AI skill extraction.'),
                        'data' => new OA\Schema(ref: '#/components/schemas/JobDescription'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Missing, invalid, or revoked Bearer token.', content: new OA\MediaType\Json(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(
                response: 422,
                description: 'Invalid title, company name, or description.',
                content: new OA\MediaType\Json(ref: '#/components/schemas/ValidationError', example: ['message' => 'The title field is required.', 'errors' => ['title' => ['The title field is required.']]]),
            ),
        ],
    )]
    public function store(): void {}

    #[OA\Operation\Get(
        path: '/api/v1/job-descriptions/{job_description}',
        operationId: 'getJobDescription',
        tags: ['Job Descriptions'],
        summary: 'Get a job description and its extracted skills',
        description: 'Returns a job description owned by the authenticated user, including description and skills. Use this endpoint to poll extraction status. A failed extraction is represented by parsing_status=failed in an HTTP 200 response; no processing_error field is returned.',
        parameters: [new OA\Parameter\Path(name: 'job_description', description: 'Job description ID.', schema: new OA\Schema(type: 'integer'), example: 1)],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Job description details.',
                content: new OA\MediaType\Json(
                    type: 'object',
                    required: ['status', 'data'],
                    properties: [
                        'status' => new OA\Schema(type: 'string', example: 'success'),
                        'data' => new OA\Schema(ref: '#/components/schemas/JobDescriptionDetail'),
                    ],
                    example: [
                        'status' => 'success',
                        'data' => [
                            'id' => 1,
                            'title' => 'Laravel Backend Developer',
                            'company_name' => 'Example Company',
                            'parsing_status' => 'completed',
                            'description' => 'We are looking for a backend developer experienced in Laravel, PHP, MySQL, and REST APIs.',
                            'skills' => [['id' => 1, 'name' => 'laravel', 'category' => 'technical', 'importance' => 0.95]],
                            'created_at' => '2026-10-30T12:00:00.000000Z',
                            'updated_at' => '2026-10-30T12:01:00.000000Z',
                        ],
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Missing, invalid, or revoked Bearer token.', content: new OA\MediaType\Json(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(
                response: 404,
                description: 'The record does not exist or belongs to another user. The response shape differs between these cases.',
                content: new OA\MediaType\Json(
                    type: 'object',
                    required: ['message'],
                    properties: [
                        'message' => new OA\Schema(type: 'string', example: 'Resource not found.'),
                        'status' => new OA\Schema(type: 'string', description: 'Only present for an existing record owned by another user.', example: 'error'),
                    ],
                    examples: [
                        new OA\Example(example: 'notOwned', summary: 'Record belongs to another user', value: ['status' => 'error', 'message' => 'Resource not found.']),
                        new OA\Example(example: 'missing', summary: 'Record does not exist (example ID 999)', value: ['message' => 'No query results for model [App\\Models\\JobDescription] 999']),
                    ],
                ),
            ),
        ],
    )]
    public function show(): void {}
}
