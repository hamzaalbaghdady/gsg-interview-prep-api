<?php

namespace App\OpenApi\Schemas;

use OpenApi\Spec as OA;

#[OA\Schema(
    component: 'ResumeUploadRequest',
    type: 'object',
    required: ['file'],
    properties: [
        'file' => new OA\Schema(type: 'string', format: 'binary', description: 'Required PDF or TXT file, maximum 5120 KB. Both its extension and detected content type are checked.'),
        'title' => new OA\Schema(type: 'string', maxLength: 45, description: 'Optional; must be non-empty when provided. If omitted, uses the filename without its extension, truncated to 45 characters.', example: 'Backend Developer CV'),
    ],
)]
final class ResumeUploadRequestSchema {}
