<?php

namespace App\Services\Ai;

use App\Exceptions\AiServiceException;
use GuzzleHttp\Exception\ConnectTimeoutException;
use GuzzleHttp\Exception\NetworkTimeoutException;
use GuzzleHttp\Exception\ResponseTimeoutException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\TimeoutExceededException;
use Throwable;

class AiFailure
{
    public static function code(?Throwable $exception): string
    {
        if ($exception instanceof AiServiceException) {
            return $exception->errorCode;
        }

        if ($exception instanceof ConnectionException) {
            $previous = $exception->getPrevious();
            if ($previous instanceof ConnectTimeoutException
                || $previous instanceof NetworkTimeoutException
                || $previous instanceof ResponseTimeoutException) {
                return 'timeout';
            }

            return 'connection_failed';
        }

        return $exception instanceof TimeoutExceededException ? 'timeout' : 'unexpected_error';
    }

    public static function automaticRetry(string $code): bool
    {
        return in_array($code, ['connection_failed', 'timeout', 'rate_limited', 'service_unavailable', 'invalid_response'], true);
    }

    public static function details(Model $record, string $statusColumn): ?array
    {
        if ($record->getRawOriginal($statusColumn) !== 'failed') {
            return null;
        }

        // Older failed records have no stored code, so show a generic message.
        $code = $record->ai_error_code ?? 'unexpected_error';

        return [
            'error_code' => $code,
            'error_message' => self::message($code),
            // Manual retries always recheck current data and configuration, even for permanent errors.
            'can_retry' => true,
        ];
    }

    public static function message(string $code): string
    {
        return match ($code) {
            'connection_failed' => 'Could not connect to the AI service. Please try again.',
            'timeout' => 'AI processing exceeded the allowed time. Please try again.',
            'rate_limited' => 'The AI service is busy. Please try again later.',
            'service_unavailable' => 'The AI service is temporarily unavailable. Please try again later.',
            'invalid_response' => 'The AI service did not return a complete, valid result. Please try again.',
            'service_configuration' => 'The AI service is unavailable because of a configuration issue. Please contact support, then try again.',
            'input_rejected' => 'The AI service could not process the supplied data. Please review it before trying again.',
            'request_rejected' => 'The AI service rejected this request. Please contact support, then try again.',
            'file_unavailable' => 'The uploaded resume file is unavailable. Please contact support to restore it, then try again.',
            'missing_input' => 'Required data is missing. Please complete the previous processing steps, then try again.',
            'queue_unavailable' => 'Processing could not be queued. Please try again later.',
            default => 'Processing failed unexpectedly. Please try again later.',
        };
    }
}
