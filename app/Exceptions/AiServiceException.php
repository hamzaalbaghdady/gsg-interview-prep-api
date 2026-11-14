<?php

namespace App\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

class AiServiceException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, ?Throwable $previous = null)
    {
        parent::__construct($errorCode, 0, $previous);
    }

    public static function fromResponse(Response $response): self
    {
        $code = match (true) {
            $response->status() === 429 => 'rate_limited',
            in_array($response->status(), [408, 504], true) => 'timeout',
            $response->serverError() => 'service_unavailable',
            in_array($response->status(), [401, 403], true) => 'service_configuration',
            $response->status() === 422 => 'input_rejected',
            default => 'request_rejected',
        };

        return new self($code);
    }
}
