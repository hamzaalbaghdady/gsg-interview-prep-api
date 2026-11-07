<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreInterviewRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'resume_id' => ['required', 'integer', 'min:1'],
            'job_description_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
