<?php

namespace App\OpenApi\Schemas;

use OpenApi\Spec as OA;

#[OA\Schema(
    component: 'AiRetryError',
    type: 'object',
    required: ['message'],
    properties: ['message' => new OA\Schema(type: 'string', example: 'Only failed operations can be retried.')],
)]
final class AiRetryErrorSchema {}
