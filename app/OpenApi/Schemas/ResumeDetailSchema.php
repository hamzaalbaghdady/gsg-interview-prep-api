<?php

namespace App\OpenApi\Schemas;

use OpenApi\Spec as OA;

#[OA\Schema(
    component: 'ResumeDetail',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/Resume'),
        new OA\Schema(
            type: 'object',
            required: ['extracted_text', 'skills'],
            properties: [
                'ai_failure' => new OA\Schema(ref: '#/components/schemas/AiFailure'),
                'extracted_text' => new OA\Schema(type: 'string', nullable: true, description: 'May be null before processing completes.', example: 'Backend developer experienced in Laravel and MySQL.'),
                'skills' => new OA\Schema(
                    type: 'array',
                    description: 'Extracted skills. May be empty before processing completes.',
                    items: new OA\Schema(
                        type: 'object',
                        required: ['id', 'name', 'category', 'confidence_score'],
                        properties: [
                            'id' => new OA\Schema(type: 'integer', example: 1),
                            'name' => new OA\Schema(type: 'string', example: 'Laravel'),
                            'category' => new OA\Schema(type: 'string', example: 'technical'),
                            'confidence_score' => new OA\Schema(type: 'number', nullable: true, example: 0.95),
                        ],
                    ),
                ),
            ],
        ),
    ],
)]
final class ResumeDetailSchema {}
