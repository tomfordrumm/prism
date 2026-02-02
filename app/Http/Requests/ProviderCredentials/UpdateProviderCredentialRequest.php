<?php

namespace App\Http\Requests\ProviderCredentials;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProviderCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $metadata = $this->input('metadata');
        if (is_array($metadata)) {
            $this->merge([
                'metadata' => $this->normalizeMetadata($metadata),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'in:openai,anthropic,google,openrouter'],
            'name' => ['required', 'string', 'max:255'],
            'api_key' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    private function normalizeMetadata(array $metadata): array
    {
        $baseUrl = $metadata['base_url'] ?? $metadata['baseUrl'] ?? null;
        if (! is_string($baseUrl) || $baseUrl === '') {
            return $metadata;
        }

        $normalized = $this->normalizeBaseUrl($baseUrl);
        $metadata['base_url'] = $normalized;
        unset($metadata['baseUrl']);

        return $metadata;
    }

    private function normalizeBaseUrl(string $baseUrl): string
    {
        $normalized = rtrim(trim($baseUrl), '/');
        $normalized = preg_replace('#/(v1/)?chat/completions$#', '', $normalized) ?? $normalized;
        $normalized = preg_replace('#/(v1/)?responses$#', '', $normalized) ?? $normalized;

        return $normalized;
    }
}
