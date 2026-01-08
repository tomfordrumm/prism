<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'improvement_provider_credential_id' => [
                'nullable',
                'integer',
                'required_with:improvement_model_name',
                Rule::exists('provider_credentials', 'id')
                    ->where('tenant_id', currentTenantId()),
            ],
            'improvement_model_name' => ['nullable', 'string', 'required_with:improvement_provider_credential_id'],
        ];
    }
}
