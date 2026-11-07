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
        'status' => new OA\Schema(type: 'string', enum: ['ready', 'in_progress', 'completed', 'failed'], example: 'ready'),
        'total_questions' => new OA\Schema(type: 'integer', enum: [Interview::TOTAL_QUESTIONS], example: Interview::TOTAL_QUESTIONS),
        'started_at' => new OA\Schema(type: 'string', format: 'date-time', nullable: true),
        'completed_at' => new OA\Schema(type: 'string', format: 'date-time', nullable: true),
    ],
)]
final class InterviewSchema {}
