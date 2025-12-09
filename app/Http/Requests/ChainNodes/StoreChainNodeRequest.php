<?php

namespace App\Http\Requests\ChainNodes;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChainNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        /** @var Project|null $project */
        $project = $this->route('project');

        return [
            'name' => ['required', 'string', 'max:255'],
            'provider_credential_id' => [
                'required',
                'integer',
                Rule::exists('provider_credentials', 'id')->where(
                    fn ($query) => $query->where('tenant_id', currentTenantId())
                ),
            ],
            'model_name' => ['required', 'string', 'max:255'],
            'model_params' => ['nullable', 'array'],
            'messages_config' => ['required', 'array', 'min:1'],
            'messages_config.*.role' => ['required', 'string', 'in:system,user,assistant'],
            'messages_config.*.mode' => ['nullable', 'string', Rule::in(['template', 'inline'])],
            'messages_config.*.prompt_template_id' => [
                'nullable',
                'integer',
                Rule::exists('prompt_templates', 'id')->where(function ($query) use ($project) {
                    $query->where('tenant_id', currentTenantId());

                    if ($project) {
                        $query->where('project_id', $project->id);
                    }
                }),
            ],
            'messages_config.*.prompt_version_id' => [
                'nullable',
                'integer',
                Rule::exists('prompt_versions', 'id')->where(fn ($query) => $query->where('tenant_id', currentTenantId())),
            ],
            'messages_config.*.inline_content' => ['nullable', 'string'],
            'output_schema' => ['nullable', 'array'],
            'stop_on_validation_error' => ['boolean'],
            'order_index' => ['nullable', 'integer', 'min:1'],
            'messages_config.*.variables' => ['nullable', 'array'],
            'messages_config.*.variables.*.source' => ['nullable', 'string', Rule::in(['input', 'previous_step', 'constant'])],
            'messages_config.*.variables.*.path' => ['nullable', 'string'],
            'messages_config.*.variables.*.step_key' => ['nullable', 'string'],
            'messages_config.*.variables.*.value' => ['nullable'],
            'output_schema_definition' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $configs = $this->input('messages_config', []);

            foreach ($configs as $index => $config) {
                $mode = $config['mode'] ?? 'template';

                if ($mode === 'template') {
                    $templateId = $config['prompt_template_id'] ?? null;
                    $versionId = $config['prompt_version_id'] ?? null;

                    if (! $templateId) {
                        $validator->errors()->add("messages_config.$index.prompt_template_id", 'Template is required in template mode.');
                        continue;
                    }

                    if ($versionId !== null) {
                        $exists = \App\Models\PromptVersion::query()
                            ->where('id', $versionId)
                            ->where('prompt_template_id', $templateId)
                            ->where('tenant_id', currentTenantId())
                            ->exists();

                        if (! $exists) {
                            $validator->errors()->add("messages_config.$index.prompt_version_id", 'Version does not belong to the selected template.');
                        }
                    }
                }

                if ($mode === 'inline' && empty($config['inline_content'])) {
                    $validator->errors()->add("messages_config.$index.inline_content", 'Inline content is required in custom prompt mode.');
                }
            }
        });
    }
}
