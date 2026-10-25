<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreResumeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:45'],
            'file' => ['required', 'file', 'mimes:pdf,txt', 'extensions:pdf,txt', 'max:5120'],
        ];
    }
}
