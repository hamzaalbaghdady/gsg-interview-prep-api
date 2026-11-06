<?php

namespace App\OpenApi\Schemas;

use OpenApi\Spec as OA;

#[OA\Schema(
    component: 'ValidationError',
    type: 'object',
    required: ['message', 'errors'],
    properties: [
        'message' => new OA\Schema(type: 'string', example: 'The email field is required.'),
        'errors' => new OA\Schema(
            type: 'object',
            description: 'Field names mapped to lists of validation messages. Messages depend on the submitted data.',
            additionalProperties: new OA\Schema(type: 'array', items: new OA\Schema(type: 'string')),
            example: ['email' => ['The email field is required.']],
        ),
    ],
)]
final class ValidationErrorSchema {}
