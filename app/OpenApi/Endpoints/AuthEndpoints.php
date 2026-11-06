<?php

namespace App\OpenApi\Endpoints;

use OpenApi\Spec as OA;

final class AuthEndpoints
{
    #[OA\Operation\Post(
        path: '/api/v1/register',
        operationId: 'register',
        tags: ['Authentication'],
        summary: 'Create an account',
        description: 'Creates a user and returns a Sanctum personal access token. Use the token in Authorize to call protected endpoints.',
        security: [],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType\Json(ref: '#/components/schemas/RegisterRequest'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Account created.',
                content: new OA\MediaType\Json(
                    type: 'object',
                    required: ['user', 'token'],
                    properties: [
                        'user' => new OA\Schema(ref: '#/components/schemas/User'),
                        'token' => new OA\Schema(type: 'string', description: 'Sanctum personal access token; this is not a JWT.', example: '1|example-token'),
                    ],
                ),
            ),
            new OA\Response(
                response: 422,
                description: 'Invalid input, an email already in use, or a password confirmation mismatch.',
                content: new OA\MediaType\Json(ref: '#/components/schemas/ValidationError'),
            ),
        ],
    )]
    public function register(): void {}

    #[OA\Operation\Post(
        path: '/api/v1/login',
        operationId: 'login',
        tags: ['Authentication'],
        summary: 'Log in',
        description: 'Returns a new Sanctum personal access token for valid credentials.',
        security: [],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType\Json(ref: '#/components/schemas/LoginRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login successful.',
                content: new OA\MediaType\Json(
                    type: 'object',
                    required: ['status', 'message', 'user', 'token'],
                    properties: [
                        'status' => new OA\Schema(type: 'string', example: 'success'),
                        'message' => new OA\Schema(type: 'string', example: 'User logged in successfully.'),
                        'user' => new OA\Schema(ref: '#/components/schemas/User'),
                        'token' => new OA\Schema(type: 'string', description: 'Paste this token into Authorize without the Bearer prefix.', example: '2|example-token'),
                    ],
                ),
            ),
            new OA\Response(
                response: 401,
                description: 'Incorrect credentials.',
                content: new OA\MediaType\Json(
                    type: 'object',
                    required: ['status', 'message'],
                    properties: [
                        'status' => new OA\Schema(type: 'string', example: 'Failed'),
                        'message' => new OA\Schema(type: 'string', example: 'Your credentials are incorrect.'),
                    ],
                ),
            ),
            new OA\Response(
                response: 422,
                description: 'Invalid login input.',
                content: new OA\MediaType\Json(ref: '#/components/schemas/ValidationError'),
            ),
        ],
    )]
    public function login(): void {}

    #[OA\Operation\Post(
        path: '/api/v1/logout',
        operationId: 'logout',
        tags: ['Authentication'],
        summary: 'Log out',
        description: 'Revokes only the Bearer token used for this request. Other tokens remain valid. Clear the revoked token from Authorize after logging out.',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Current token revoked.',
                content: new OA\MediaType\Json(
                    type: 'object',
                    required: ['status', 'message'],
                    properties: [
                        'status' => new OA\Schema(type: 'string', example: 'success'),
                        'message' => new OA\Schema(type: 'string', example: 'Successfully logged out.'),
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
    public function logout(): void {}
}
