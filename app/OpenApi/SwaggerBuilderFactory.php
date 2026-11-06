<?php

namespace App\OpenApi;

use L5Swagger\CustomBuilderInterface;
use OpenApi\Builder;

final class SwaggerBuilderFactory implements CustomBuilderInterface
{
    public function create(): Builder
    {
        return new Builder;
    }
}
