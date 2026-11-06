<?php

namespace App\OpenApi\Schemas;

use OpenApi\Spec as OA;

#[OA\Schema(
    component: 'User',
    type: 'object',
    required: ['id', 'name', 'email', 'created_at', 'updated_at'],
    properties: [
        'id' => new OA\Schema(type: 'integer', example: 1),
        'name' => new OA\Schema(type: 'string', example: 'Mahmoud'),
        'email' => new OA\Schema(type: 'string', format: 'email', example: 'mahmoud@example.com'),
        'email_verified_at' => new OA\Schema(type: 'string', format: 'date-time', nullable: true, description: 'May be omitted in the registration response.', example: null),
        'created_at' => new OA\Schema(type: 'string', format: 'date-time', nullable: true, example: '2026-10-30T12:00:00.000000Z'),
        'updated_at' => new OA\Schema(type: 'string', format: 'date-time', nullable: true, example: '2026-10-30T12:00:00.000000Z'),
    ],
)]
final class UserSchema {}
