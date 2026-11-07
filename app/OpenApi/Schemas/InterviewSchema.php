<?php

namespace App\OpenApi\Schemas;

use App\Models\Interview;
use OpenApi\Spec as OA;

#[OA\Schema(
    component: 'Interview',
    type: 'object',
    required: ['id', 'resume_id', 'job_description_id', 'status', 'total_questions', 'started_at', 'completed_at'],
    properties: [
        'id' => new OA\Schema(type: 'integer', example: 42),
        'resume_id' => new OA\Schema(type: 'integer', example: 12),
        'job_description_id' => new OA\Schema(type: 'integer', example: 34),
        'status' => new OA\Schema(
            type: 'string',
            description: 'An interview is preparing while its initial questions are being generated, ready once those questions are saved, and in progress after the first answer is saved. It ends as completed or failed.',
            enum: ['preparing', 'ready', 'in_progress', 'completed', 'failed'],
            example: 'preparing',
        ),
        'total_questions' => new OA\Schema(type: 'integer', description: 'Number of initial interview questions.', enum: [Interview::INITIAL_QUESTION_COUNT], example: Interview::INITIAL_QUESTION_COUNT),
        'started_at' => new OA\Schema(type: 'string', format: 'date-time', nullable: true),
        'completed_at' => new OA\Schema(type: 'string', format: 'date-time', nullable: true),
    ],
)]
final class InterviewSchema {}
