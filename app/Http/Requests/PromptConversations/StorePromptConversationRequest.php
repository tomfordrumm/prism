<?php

namespace App\Http\Requests\PromptConversations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePromptConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['idea', 'run_feedback'])],
            'run_id' => ['nullable', 'integer'],
            'run_step_id' => ['nullable', 'integer'],
            'target_prompt_version_id' => ['nullable', 'integer'],
            'initial_message' => ['nullable', 'string'],
        ];
    }
}
