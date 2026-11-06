<?php

namespace App\OpenApi\Schemas;

use OpenApi\Spec as OA;

#[OA\Schema(
    component: 'JobDescriptionDetail',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/JobDescription'),
        new OA\Schema(
            type: 'object',
            required: ['description', 'skills'],
            properties: [
                'description' => new OA\Schema(type: 'string', nullable: true, example: 'We are looking for a backend developer experienced in Laravel, PHP, MySQL, and REST APIs.'),
                'skills' => new OA\Schema(
                    type: 'array',
                    description: 'Extracted skills. May be empty before processing completes.',
                    items: new OA\Schema(
                        type: 'object',
                        required: ['id', 'name', 'category', 'importance'],
                        properties: [
                            'id' => new OA\Schema(type: 'integer', example: 1),
                            'name' => new OA\Schema(type: 'string', example: 'laravel'),
                            'category' => new OA\Schema(type: 'string', example: 'technical'),
                            'importance' => new OA\Schema(type: 'number', nullable: true, minimum: 0, maximum: 1, example: 0.95),
                        ],
                    ),
                ),
            ],
        ),
    ],
)]
final class JobDescriptionDetailSchema {}
