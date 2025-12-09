<?php

namespace App\Http\Requests\PromptTemplates;

use Illuminate\Foundation\Http\FormRequest;

class StorePromptTemplateRequest extends FormRequest
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
            'initial_content' => ['required', 'string'],
            'initial_changelog' => ['nullable', 'string'],
        ];
    }
}
