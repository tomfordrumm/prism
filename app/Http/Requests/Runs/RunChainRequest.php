<?php

namespace App\Http\Requests\Runs;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RunChainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'input' => ['nullable', 'json'],
            'dataset_id' => ['nullable', 'integer', Rule::exists('datasets', 'id')->where(
                fn ($query) => $query->where('tenant_id', currentTenantId())
            )],
        ];
    }
}
