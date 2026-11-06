<?php

namespace App\OpenApi\Schemas;

use OpenApi\Spec as OA;

#[OA\Schema(
    component: 'LoginRequest',
    type: 'object',
    required: ['email', 'password'],
    properties: [
        'email' => new OA\Schema(type: 'string', format: 'email', example: 'mahmoud@example.com'),
        'password' => new OA\Schema(type: 'string', format: 'password', minLength: 8, writeOnly: true, example: 'ExamplePassword123!'),
    ],
)]
final class LoginRequestSchema {}
