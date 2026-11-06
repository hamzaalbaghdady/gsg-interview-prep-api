<?php

namespace App\OpenApi\Schemas;

use OpenApi\Spec as OA;

#[OA\Schema(
    component: 'RegisterRequest',
    type: 'object',
    required: ['name', 'email', 'password', 'password_confirmation'],
    properties: [
        'name' => new OA\Schema(type: 'string', maxLength: 255, example: 'Mahmoud'),
        'email' => new OA\Schema(type: 'string', format: 'email', description: 'Must not already be registered.', example: 'mahmoud@example.com'),
        'password' => new OA\Schema(type: 'string', format: 'password', minLength: 8, writeOnly: true, example: 'ExamplePassword123!'),
        'password_confirmation' => new OA\Schema(type: 'string', format: 'password', description: 'Must match password.', writeOnly: true, example: 'ExamplePassword123!'),
    ],
)]
final class RegisterRequestSchema {}
