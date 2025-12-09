<?php

namespace App\Http\Requests\TestCases;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTestCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'input_variables' => ['required', 'array'],
            'tags' => ['nullable', 'array'],
        ];
    }
}
