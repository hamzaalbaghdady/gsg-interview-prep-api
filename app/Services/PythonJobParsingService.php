<?php

namespace App\Services;

use App\Exceptions\AiServiceException;
use App\Models\JobDescription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class PythonJobParsingService
{
    /**
     * Send job description to Python AI service and validate returning dataset.
     */
    public function extractSkills(JobDescription $job): array
    {
        $url = config('services.python_ai.job_url');

        // Check and validate the url
        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)
            || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new AiServiceException('service_configuration');
        }

        // Prepare the request header and configurations
        $request = Http::acceptJson()
            ->connectTimeout(max(1, min((int) config('services.python_ai.connect_timeout', 5), 5)))
            ->timeout(max(1, min((int) config('services.python_ai.timeout', 30), 30)));

        // Inject authorization bearer token if required by the AI layer
        if ($token = config('services.python_ai.token')) {
            $request = $request->withToken($token);
        }

        // Send raw text payload to the custom extraction endpoint
        $response = $request->post($url, [
            'text' => $job->description,
        ]);

        // Throw exception if request not successful
        if (! $response->successful()) {
            throw AiServiceException::fromResponse($response);
        }

        // Parse json to array
        $payload = $response->json();

        // Check array
        if (! is_array($payload)) {
            throw new AiServiceException('invalid_response');
        }

        // Ensure the Python response structure is completely clean
        $validator = Validator::make($payload, [
            'skills' => ['present', 'array', 'list', 'max:500'],
            'skills.*' => ['required', 'array:name,category,importance'],
            'skills.*.name' => ['required', 'string', 'max:45'],
            'skills.*.category' => ['required', 'string', 'max:45'],
            'skills.*.importance' => ['present', 'nullable', 'numeric', 'between:0,1'],
        ]);

        // Throw exception if validator fails
        if ($validator->fails()) {
            throw new AiServiceException('invalid_response');
        }

        return $validator->validated();
    }
}
