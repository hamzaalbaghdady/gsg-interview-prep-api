<?php

namespace App\Services;

use App\Models\Resume;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class PythonResumeService
{
    public function extract(Resume $resume): array
    {
        $url = config('resumes.python_url');

        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)
            || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new RuntimeException('Resume processing service is not configured.');
        }

        $disk = Storage::disk('local');
        $stream = $disk->readStream($resume->file_path);

        if (! is_resource($stream)) {
            throw new RuntimeException('Resume file is unavailable.');
        }

        try {
            $request = Http::acceptJson()
                ->connectTimeout(config('resumes.connect_timeout'))
                ->timeout(config('resumes.timeout'));

            if ($token = config('resumes.python_token')) {
                $request = $request->withToken($token);
            }

            $response = $request->attach(
                config('resumes.file_field'),
                $stream,
                basename($resume->file_path),
                ['Content-Type' => $disk->mimeType($resume->file_path) ?: 'application/octet-stream']
            )->post($url);
        } finally {
            fclose($stream);
        }

        if (! $response->successful()) {
            // Do not include upstream response bodies or resume contents in errors.
            throw new RuntimeException('Resume processing service failed.');
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('Resume processing service returned invalid JSON.');
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
            throw new RuntimeException('Resume processing service returned an invalid result.');
        }

        return $validator->validated();
    }
}
