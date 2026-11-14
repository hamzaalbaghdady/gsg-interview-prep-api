<?php

namespace App\Services;

use App\Exceptions\AiServiceException;
use App\Models\Resume;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use League\Flysystem\FilesystemException;

class PythonResumeService
{
    public function extract(Resume $resume): array
    {
        $url = config('resumes.python_url');

        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)
            || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new AiServiceException('service_configuration');
        }

        // Leave time for failure handling before the 45-second queue job deadline.
        $request = Http::acceptJson()->connectTimeout(5)->timeout(max(1, min((int) config('resumes.timeout', 30), 30)));

        if ($token = config('resumes.python_token')) {
            $request = $request->withToken($token);
        }

        $disk = Storage::disk('local');
        $stream = null;

        try {
            if (! $disk->exists($resume->file_path)) {
                throw new AiServiceException('file_unavailable');
            }

            $stream = $disk->readStream($resume->file_path);

            if (! is_resource($stream)) {
                throw new AiServiceException('file_unavailable');
            }

            $response = $request->attach(
                config('resumes.file_field'),
                $stream,
                basename($resume->file_path),
                ['Content-Type' => $disk->mimeType($resume->file_path) ?: 'application/octet-stream']
            )->post($url);
        } catch (FilesystemException $exception) {
            // Storage can throw instead of returning false; preserve the cause without exposing it.
            throw new AiServiceException('file_unavailable', $exception);
        } finally {
            // Some transports close the stream themselves, including on failed uploads.
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $response->successful()) {
            throw AiServiceException::fromResponse($response);
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new AiServiceException('invalid_response');
        }

        $validator = Validator::make($payload, [
            'extracted_text' => ['required', 'string', 'max:16777215'],
            'skills' => ['present', 'array', 'list', 'max:500'],
            'skills.*' => ['required', 'array:name,category,confidence_score'],
            'skills.*.name' => ['required', 'string', 'max:45'],
            'skills.*.category' => ['required', 'string', 'max:45'],
            'skills.*.confidence_score' => ['present', 'nullable', 'numeric', 'between:0,1'],
        ]);

        if ($validator->fails() || trim($payload['extracted_text'] ?? '') === ''
            || strlen($payload['extracted_text']) > 16777215) {
            throw new AiServiceException('invalid_response');
        }

        return $validator->validated();
    }
}
