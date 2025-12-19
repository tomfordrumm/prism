<?php

namespace App\Http\Requests\PromptTemplates;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RunPromptTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'provider_credential_id' => [
                'required',
                'integer',
                Rule::exists('provider_credentials', 'id')
                    ->where(fn ($query) => $query->where('tenant_id', currentTenantId())),
            ],
            'model_name' => ['required', 'string'],
            'variables' => ['nullable', 'json'],
        ];
    }
}
