<?php

namespace App\Http\Requests\ProviderCredentials;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProviderCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'in:openai,anthropic,google'],
            'name' => ['required', 'string', 'max:255'],
            'api_key' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
