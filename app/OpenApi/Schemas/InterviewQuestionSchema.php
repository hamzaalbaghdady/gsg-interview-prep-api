<?php

namespace App\OpenApi\Schemas;

use OpenApi\Spec as OA;

#[OA\Schema(
    component: 'InterviewQuestion',
    type: 'object',
    required: ['id', 'sequence_number', 'text', 'type'],
    properties: [
        'id' => new OA\Schema(type: 'integer', description: 'Database question ID used in answer and answer-retry URLs.'),
        'sequence_number' => new OA\Schema(type: 'integer', description: 'Position in the interview; not the URL question ID.'),
        'text' => new OA\Schema(type: 'string'),
        'type' => new OA\Schema(type: 'string'),
        'answer' => new OA\Schema(type: 'object', nullable: true, description: 'Included when the answer relation is loaded; null if unanswered.', properties: [
            'id' => new OA\Schema(type: 'integer', description: 'Saved answer ID; do not use this as the question URL parameter.'),
            'text' => new OA\Schema(type: 'string'),
            'evaluation_status' => new OA\Schema(type: 'string', enum: ['pending', 'in_progress', 'completed', 'failed']),
            'score' => new OA\Schema(type: 'number', nullable: true, minimum: 0, maximum: 10),
            'better_answer' => new OA\Schema(type: 'string', nullable: true),
            'ai_failure' => new OA\Schema(ref: '#/components/schemas/AiFailure'),
            'created_at' => new OA\Schema(type: 'string', format: 'date-time', nullable: true),
        ]),
    ],
)]
final class InterviewQuestionSchema {}
