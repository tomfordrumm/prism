<?php

namespace App\Http\Requests\Chains;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }
}
