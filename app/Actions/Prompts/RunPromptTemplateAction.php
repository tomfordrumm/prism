<?php

namespace App\Actions\Prompts;

use App\Models\Project;
use App\Models\PromptTemplate;
use App\Models\ProviderCredential;
use App\Models\Run;

class RunPromptTemplateAction
{
    public function run(
        Project $project,
        PromptTemplate $template,
        ProviderCredential $credential,
        string $modelName,
        array $variables
    ): Run {
        $latestVersion = $template->promptVersions()->orderByDesc('version')->firstOrFail();

        $snapshot = [[
            'id' => null,
            'name' => $template->name,
            'provider_credential_id' => $credential->id,
            'model_name' => $modelName,
            'model_params' => null,
            'messages_config' => [
                [
                    'role' => 'user',
                    'mode' => 'template',
                    'prompt_template_id' => $template->id,
                    'prompt_version_id' => $latestVersion->id,
                ],
            ],
            'output_schema' => null,
            'stop_on_validation_error' => false,
            'order_index' => 1,
        ]];

        return Run::create([
            'tenant_id' => currentTenantId(),
            'project_id' => $project->id,
            'chain_id' => null,
            'chain_snapshot' => $snapshot,
            'input' => $variables,
            'status' => 'pending',
            'dataset_id' => null,
            'test_case_id' => null,
            'started_at' => now(),
        ]);
    }
}
