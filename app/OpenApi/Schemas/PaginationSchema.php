<?php

namespace App\OpenApi\Schemas;

use OpenApi\Spec as OA;

#[OA\Schema(
    component: 'PaginationLinks',
    type: 'object',
    required: ['first', 'last', 'prev', 'next'],
    properties: [
        'first' => new OA\Schema(type: 'string', format: 'uri', nullable: true, example: 'http://localhost:8000/api/v1/resumes?page=1'),
        'last' => new OA\Schema(type: 'string', format: 'uri', nullable: true, example: 'http://localhost:8000/api/v1/resumes?page=1'),
        'prev' => new OA\Schema(type: 'string', format: 'uri', nullable: true, example: null),
        'next' => new OA\Schema(type: 'string', format: 'uri', nullable: true, example: null),
    ],
)]
#[OA\Schema(
    component: 'PaginationMeta',
    type: 'object',
    required: ['current_page', 'from', 'last_page', 'links', 'path', 'per_page', 'to', 'total'],
    properties: [
        'current_page' => new OA\Schema(type: 'integer', example: 1),
        'from' => new OA\Schema(type: 'integer', nullable: true, example: 1),
        'last_page' => new OA\Schema(type: 'integer', example: 1),
        'links' => new OA\Schema(
            type: 'array',
            items: new OA\Schema(
                type: 'object',
                required: ['url', 'label', 'active'],
                properties: [
                    'url' => new OA\Schema(type: 'string', format: 'uri', nullable: true, example: 'http://localhost:8000/api/v1/resumes?page=1'),
                    'label' => new OA\Schema(type: 'string', example: '1'),
                    'active' => new OA\Schema(type: 'boolean', example: true),
                ],
            ),
        ),
        'path' => new OA\Schema(type: 'string', format: 'uri', example: 'http://localhost:8000/api/v1/resumes'),
        'per_page' => new OA\Schema(type: 'integer', example: 15),
        'to' => new OA\Schema(type: 'integer', nullable: true, example: 1),
        'total' => new OA\Schema(type: 'integer', example: 1),
    ],
)]
final class PaginationSchema {}
