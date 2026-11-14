<?php

namespace App\OpenApi\Schemas;

use OpenApi\Spec as OA;

#[OA\Schema(
    component: 'InterviewAnswerSubmission',
    type: 'object',
    required: ['data'],
    properties: [
        'data' => new OA\Schema(
            type: 'object',
            required: ['answer_id', 'evaluation_status', 'ai_failure'],
            properties: [
                'ai_failure' => new OA\Schema(ref: '#/components/schemas/AiFailure'),
                'answer_id' => new OA\Schema(type: 'integer', example: 101),
                'evaluation_status' => new OA\Schema(
                    type: 'string',
                    enum: ['pending', 'in_progress', 'completed', 'failed'],
                    example: 'pending',
                ),
            ],
        ),
    ],
)]
final class InterviewAnswerSubmissionSchema {}
