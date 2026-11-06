<?php

namespace App\OpenApi\Endpoints;

use OpenApi\Spec as OA;

final class UserEndpoints
{
    #[OA\Operation\Get(
        path: '/api/v1/me',
        operationId: 'getCurrentUser',
        tags: ['User'],
        summary: 'Get the authenticated user',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authenticated user details.',
                content: new OA\MediaType\Json(
                    type: 'object',
                    required: ['status', 'data'],
                    properties: [
                        'status' => new OA\Schema(type: 'string', example: 'success'),
                        'data' => new OA\Schema(ref: '#/components/schemas/User'),
                    ],
                ),
            ),
            new OA\Response(
                response: 401,
                description: 'Missing, invalid, or revoked Bearer token.',
                content: new OA\MediaType\Json(ref: '#/components/schemas/UnauthenticatedError'),
            ),
        ],
    )]
    public function show(): void {}
}
