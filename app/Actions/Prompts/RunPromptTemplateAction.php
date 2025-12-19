<?php

namespace App\Actions\Prompts;

use App\Models\Chain;
use App\Models\ChainNode;
use App\Models\Project;
use App\Models\PromptTemplate;
use App\Models\ProviderCredential;
use App\Models\Run;
use App\Services\Runs\ChainSnapshotLoader;

class RunPromptTemplateAction
{
    public function __construct(private ChainSnapshotLoader $snapshotLoader)
    {
    }

    public function run(
        Project $project,
        PromptTemplate $template,
        ProviderCredential $credential,
        string $modelName,
        array $variables
    ): Run {
        $latestVersion = $template->promptVersions()->orderByDesc('version')->firstOrFail();

        $chain = Chain::create([
            'tenant_id' => currentTenantId(),
            'project_id' => $project->id,
            'name' => 'Prompt: '.$template->name,
            'description' => 'Quick prompt run',
            'is_active' => false,
            'is_quick_prompt' => true,
        ]);

        $node = ChainNode::create([
            'tenant_id' => currentTenantId(),
            'chain_id' => $chain->id,
            'name' => $template->name,
            'order_index' => 1,
            'provider_credential_id' => $credential->id,
            'model_name' => $modelName,
            'model_params' => null,
            'messages_config' => [
                [
                    'role' => 'user',
                    'mode' => 'template',
                    'prompt_version_id' => $latestVersion->id,
                ],
            ],
            'output_schema' => null,
            'stop_on_validation_error' => false,
        ]);

        $chain->setRelation('nodes', collect([$node]));
        $snapshot = $this->snapshotLoader->createSnapshot($chain);

        return Run::create([
            'tenant_id' => currentTenantId(),
            'project_id' => $project->id,
            'chain_id' => $chain->id,
            'chain_snapshot' => $snapshot,
            'input' => $variables,
            'status' => 'pending',
            'dataset_id' => null,
            'test_case_id' => null,
            'started_at' => now(),
        ]);
    }
}
