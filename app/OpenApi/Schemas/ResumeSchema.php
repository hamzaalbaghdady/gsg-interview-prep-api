<?php

namespace App\OpenApi\Schemas;

use OpenApi\Spec as OA;

#[OA\Schema(
    component: 'Resume',
    type: 'object',
    required: ['id', 'title', 'parsing_status', 'processing_error', 'created_at', 'updated_at'],
    properties: [
        'id' => new OA\Schema(type: 'integer', example: 1),
        'title' => new OA\Schema(type: 'string', maxLength: 45, example: 'Backend Developer CV'),
        'parsing_status' => new OA\Schema(type: 'string', enum: ['pending', 'processing', 'completed', 'failed'], description: 'pending: queued; processing: analysis has started; completed: results are available; failed: processing could not start or ultimately failed.', example: 'pending'),
        'processing_error' => new OA\Schema(
            type: 'string',
            nullable: true,
            description: 'Null unless parsing_status is failed; then returns "Resume processing failed. Please try again later."',
            example: null,
        ),
        'created_at' => new OA\Schema(type: 'string', format: 'date-time', nullable: true, example: '2026-10-30T12:00:00.000000Z'),
        'updated_at' => new OA\Schema(type: 'string', format: 'date-time', nullable: true, example: '2026-10-30T12:00:00.000000Z'),
    ],
)]
final class ResumeSchema {}
