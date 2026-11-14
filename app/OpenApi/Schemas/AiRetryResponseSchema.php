<?php

namespace App\OpenApi\Schemas;

use OpenApi\Spec as OA;

#[OA\Schema(
    component: 'AiRetryResponse',
    type: 'object',
    required: ['message', 'data'],
    properties: [
        'message' => new OA\Schema(type: 'string', example: 'Processing has been queued again.'),
        'data' => new OA\Schema(type: 'object', required: ['id', 'status', 'ai_failure'], properties: [
            'id' => new OA\Schema(type: 'integer', description: 'Existing operation record ID; answer ID for evaluations and report ID for reports.'),
            'status' => new OA\Schema(type: 'string', description: 'Refreshed status snapshot. Normally pending, or preparing for interview questions; a worker may already have advanced it. Failed on a 503 response.'),
            'ai_failure' => new OA\Schema(ref: '#/components/schemas/AiFailure'),
        ]),
    ],
)]
final class AiRetryResponseSchema {}
