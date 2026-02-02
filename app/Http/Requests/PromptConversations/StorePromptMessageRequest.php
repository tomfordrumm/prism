<?php

namespace App\Http\Requests\PromptConversations;

use Illuminate\Foundation\Http\FormRequest;

class StorePromptMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string'],
        ];
    }
}
