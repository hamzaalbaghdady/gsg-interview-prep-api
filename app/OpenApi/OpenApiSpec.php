<?php

namespace App\OpenApi;

use OpenApi\Spec as OA;

#[OA\OpenApi(security: [new OA\Security\Requirement(scheme: 'bearerAuth')])]
#[OA\Info(
    title: 'Sparr API Documentation',
    description: 'API documentation for the interview preparation platform.',
    version: '1.0.0',
)]
#[OA\Server(url: '/', description: 'Current server')]
#[OA\Security\Scheme\Http(
    component: 'bearerAuth',
    scheme: 'bearer',
    description: 'Enter the token returned by login or registration, without the Bearer prefix.',
)]
final class OpenApiSpec {}
