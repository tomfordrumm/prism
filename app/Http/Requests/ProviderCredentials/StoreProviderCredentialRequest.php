<?php

namespace App\Http\Requests\ProviderCredentials;

use Illuminate\Foundation\Http\FormRequest;

class StoreProviderCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'in:openai,anthropic,google,openrouter'],
            'name' => ['required', 'string', 'max:255'],
            'api_key' => ['required', 'string'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
