<?php

namespace App\Http\Requests\Agents;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgentMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Message content is required.',
            'content.string' => 'Message content must be a valid string.',
            'content.max' => 'Message content may not exceed 10000 characters.',
        ];
    }
}
