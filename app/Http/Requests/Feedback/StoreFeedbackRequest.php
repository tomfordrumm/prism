<?php

namespace App\Http\Requests\Feedback;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'rating' => ['nullable', 'integer'],
            'comment' => ['required', 'string'],
            'request_suggestion' => ['boolean'],
        ];
    }
}
