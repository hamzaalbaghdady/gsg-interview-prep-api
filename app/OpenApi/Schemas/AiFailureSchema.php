<?php

namespace App\OpenApi\Schemas;

use OpenApi\Spec as OA;

#[OA\Schema(
    component: 'AiFailure',
    type: 'object',
    nullable: true,
    description: 'Null unless an AI operation has failed. Every failure allows a manual retry regardless of its error code. Messages are safe for users; internal provider responses are never exposed.',
    required: ['error_code', 'error_message', 'can_retry'],
    properties: [
        'error_code' => new OA\Schema(type: 'string', description: 'Known codes: connection_failed, timeout, rate_limited, service_unavailable, invalid_response, service_configuration, input_rejected, request_rejected, file_unavailable, missing_input, queue_unavailable, unexpected_error. Older/custom records may contain another code. Failed records without a stored code use unexpected_error. A Python HTTP 429 maps to rate_limited here and is separate from the retry endpoint HTTP 429 throttle.', example: 'service_unavailable'),
        'error_message' => new OA\Schema(type: 'string', example: 'The AI service is temporarily unavailable. Please try again later.'),
        'can_retry' => new OA\Schema(type: 'boolean', description: 'True for every failed operation. Ownership and operation-specific constraints are still checked by the retry endpoint.', example: true),
    ],
)]
final class AiFailureSchema {}
