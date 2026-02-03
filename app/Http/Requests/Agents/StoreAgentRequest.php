<?php

namespace App\Http\Requests\Agents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        /** @var \App\Models\Project|null $project */
        $project = $this->route('project');

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'system_prompt_mode' => ['required', 'in:template,inline'],
            'system_prompt_template_id' => [
                'required_if:system_prompt_mode,template',
                'integer',
                Rule::exists('prompt_templates', 'id')->where(function ($query) use ($project) {
                    $query->where('tenant_id', currentTenantId());

                    if ($project) {
                        $query->where('project_id', $project->id);
                    }
                }),
            ],
            'system_prompt_version_id' => [
                'nullable',
                'integer',
                Rule::exists('prompt_versions', 'id')->where(
                    fn ($query) => $query->where('tenant_id', currentTenantId())
                ),
            ],
            'system_inline_content' => ['nullable', 'required_if:system_prompt_mode,inline', 'string'],
            'provider_credential_id' => [
                'required',
                'integer',
                Rule::exists('provider_credentials', 'id')->where(
                    fn ($query) => $query->where('tenant_id', currentTenantId())
                ),
            ],
            'model_name' => ['required', 'string', 'max:255'],
            'model_params' => ['nullable', 'array'],
            'model_params.temperature' => ['nullable', 'numeric', 'between:0,2'],
            'model_params.max_tokens' => ['nullable', 'integer', 'min:1'],
            'max_context_messages' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $mode = $this->input('system_prompt_mode', 'inline');
            $templateId = $this->input('system_prompt_template_id');
            $versionId = $this->input('system_prompt_version_id');

            if ($mode !== 'template' || ! $versionId || ! $templateId) {
                return;
            }

            $exists = \App\Models\PromptVersion::query()
                ->where('id', $versionId)
                ->where('prompt_template_id', $templateId)
                ->where('tenant_id', currentTenantId())
                ->exists();

            if (! $exists) {
                $validator->errors()->add('system_prompt_version_id', 'Version does not belong to the selected template.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'provider_credential_id.exists' => 'The selected provider credential is invalid.',
            'model_params.temperature.between' => 'Temperature must be between 0 and 2.',
        ];
    }
}
