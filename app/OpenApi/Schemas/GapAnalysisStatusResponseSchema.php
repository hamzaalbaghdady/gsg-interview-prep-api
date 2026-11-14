<?php

namespace App\OpenApi\Schemas;

use OpenApi\Spec as OA;

#[OA\Schema(
    component: 'GapAnalysisStatusResponse',
    type: 'object',
    required: ['status', 'source', 'data'],
    properties: [
        'status' => new OA\Schema(type: 'string', example: 'success'),
        'source' => new OA\Schema(type: 'string', enum: ['calculated', 'database_cache']),
        'data' => new OA\Schema(type: 'object', description: 'Retry-related fields are documented here; also includes the existing job/resume titles, analysis, roadmap, match percentage and timestamps.', required: ['id', 'resume_id', 'job_description_id', 'roadmap_status', 'ai_failure'], properties: [
            'id' => new OA\Schema(type: 'integer', description: 'Analysis ID used in POST /gap-analyses/{analysis}/roadmap/retry.'),
            'resume_id' => new OA\Schema(type: 'integer'),
            'job_description_id' => new OA\Schema(type: 'integer'),
            'roadmap_status' => new OA\Schema(type: 'string', enum: ['pending', 'processing', 'completed', 'failed']),
            'ai_failure' => new OA\Schema(ref: '#/components/schemas/AiFailure'),
        ]),
    ],
)]
final class GapAnalysisStatusResponseSchema {}
