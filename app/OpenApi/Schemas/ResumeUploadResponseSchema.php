<?php

namespace App\OpenApi\Schemas;

use OpenApi\Spec as OA;

#[OA\Schema(
    component: 'ResumeUploadResponse',
    type: 'object',
    required: ['message', 'reused', 'data'],
    properties: [
        'message' => new OA\Schema(type: 'string', example: 'Resume uploaded and queued for processing.'),
        'reused' => new OA\Schema(type: 'boolean', description: 'True when identical file contents already exist for this user.', example: false),
        'data' => new OA\Schema(ref: '#/components/schemas/Resume'),
    ],
)]
final class ResumeUploadResponseSchema {}
