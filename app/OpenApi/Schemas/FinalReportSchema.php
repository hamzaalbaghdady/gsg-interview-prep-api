<?php

namespace App\OpenApi\Schemas;

use OpenApi\Spec as OA;

#[OA\Schema(
    component: 'FinalReport',
    type: 'object',
    required: ['id', 'interview_id', 'interview_status', 'status', 'ai_failure', 'questions', 'overall_score'],
    properties: [
        'id' => new OA\Schema(type: 'integer', description: 'Report ID. Report URLs use interview_id instead.'),
        'interview_id' => new OA\Schema(type: 'integer'),
        'interview_status' => new OA\Schema(type: 'string'),
        'status' => new OA\Schema(type: 'string', enum: ['pending', 'processing', 'completed', 'failed']),
        'ai_failure' => new OA\Schema(ref: '#/components/schemas/AiFailure'),
        'questions' => new OA\Schema(type: 'array', items: new OA\Schema(ref: '#/components/schemas/InterviewQuestion')),
        'overall_score' => new OA\Schema(type: 'number', nullable: true, minimum: 0, maximum: 100, description: 'Mean answer score multiplied by 10; calculated before initial report dispatch and preserved by report retry.'),
        'summary' => new OA\Schema(type: 'string', nullable: true),
        'improvement_areas' => new OA\Schema(nullable: true, description: 'Existing Python JSON result, null until saved; currently validated only as an array in PHP.'),
        'dimensions' => new OA\Schema(nullable: true, description: 'Existing Python JSON result, null until saved; nested shape is not validated by Laravel.'),
        'discrepancy_flag' => new OA\Schema(nullable: true, description: 'Existing Python JSON result, null until saved; nested shape is not validated by Laravel.'),
        'created_at' => new OA\Schema(type: 'string', format: 'date-time', nullable: true),
        'updated_at' => new OA\Schema(type: 'string', format: 'date-time', nullable: true),
    ],
)]
final class FinalReportSchema {}
