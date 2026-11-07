<?php

namespace App\OpenApi\Schemas;

use OpenApi\Spec as OA;

#[OA\Schema(
    component: 'InterviewRequest',
    type: 'object',
    required: ['resume_id', 'job_description_id'],
    properties: [
        'resume_id' => new OA\Schema(type: 'integer', minimum: 1, example: 12),
        'job_description_id' => new OA\Schema(type: 'integer', minimum: 1, example: 34),
    ],
)]
final class InterviewRequestSchema {}
