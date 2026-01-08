<?php

namespace App\Http\Requests\Feedback;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'rating' => ['nullable', 'integer', 'in:-1,1'],
            'comment' => ['nullable', 'string', 'required_if:request_suggestion,true', 'required_without:rating'],
            'request_suggestion' => ['boolean'],
            'provider_credential_id' => [
                'required_if:request_suggestion,true',
                'integer',
                Rule::exists('provider_credentials', 'id')
                    ->where('tenant_id', currentTenantId()),
            ],
            'model_name' => ['required_if:request_suggestion,true', 'string'],
            'target_prompt_version_id' => [
                'nullable',
                'integer',
                Rule::exists('prompt_versions', 'id')
                    ->where('tenant_id', currentTenantId()),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'provider_credential_id.required_if' => 'Please select a provider credential to request a suggestion.',
            'provider_credential_id.exists' => 'Selected credential is not available.',
            'model_name.required_if' => 'Model is required when requesting a suggestion.',
        ];
    }
}
