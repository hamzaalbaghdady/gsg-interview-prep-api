<?php

namespace App\OpenApi\Schemas;

use OpenApi\Spec as OA;

#[OA\Schema(
    component: 'UnauthenticatedError',
    type: 'object',
    required: ['message'],
    properties: [
        'message' => new OA\Schema(type: 'string', example: 'Unauthenticated.'),
    ],
)]
final class UnauthenticatedErrorSchema {}
