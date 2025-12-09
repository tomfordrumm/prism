<?php

namespace App\Http\Requests\PromptTemplates;

use Illuminate\Foundation\Http\FormRequest;

class StorePromptVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string'],
            'changelog' => ['nullable', 'string'],
        ];
    }
}
