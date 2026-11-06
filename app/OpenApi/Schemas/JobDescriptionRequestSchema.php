<?php

namespace App\OpenApi\Schemas;

use OpenApi\Spec as OA;

#[OA\Schema(
    component: 'JobDescriptionRequest',
    type: 'object',
    required: ['title'],
    properties: [
        'title' => new OA\Schema(type: 'string', minLength: 2, maxLength: 150, example: 'Laravel Backend Developer'),
        'company_name' => new OA\Schema(type: 'string', maxLength: 150, nullable: true, example: 'Example Company'),
        'description' => new OA\Schema(type: 'string', nullable: true, description: 'Optional in the API validation. When provided, this text is sent to the Python service for skill extraction.', example: 'We are looking for a backend developer experienced in Laravel, PHP, MySQL, and REST APIs.'),
    ],
)]
final class JobDescriptionRequestSchema {}
