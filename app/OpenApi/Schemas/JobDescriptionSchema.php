<?php

namespace App\OpenApi\Schemas;

use OpenApi\Spec as OA;

#[OA\Schema(
    component: 'JobDescription',
    type: 'object',
    required: ['id', 'title', 'company_name', 'parsing_status', 'created_at', 'updated_at'],
    properties: [
        'id' => new OA\Schema(type: 'integer', example: 1),
        'title' => new OA\Schema(type: 'string', maxLength: 150, example: 'Laravel Backend Developer'),
        'company_name' => new OA\Schema(type: 'string', nullable: true, example: 'Example Company'),
        'parsing_status' => new OA\Schema(type: 'string', enum: ['pending', 'processing', 'completed', 'failed'], description: 'pending: queued; processing: extraction started; completed: skills are available; failed: extraction ultimately failed.', example: 'pending'),
        'created_at' => new OA\Schema(type: 'string', format: 'date-time', nullable: true, example: '2026-10-30T12:00:00.000000Z'),
        'updated_at' => new OA\Schema(type: 'string', format: 'date-time', nullable: true, description: 'Currently null in list responses because the list query does not select this column.', example: '2026-10-30T12:00:00.000000Z'),
    ],
)]
final class JobDescriptionSchema {}
