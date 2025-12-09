<?php

namespace App\Services\Chains;

use App\Models\Chain;
use App\Models\ChainNode;
use App\Models\Dataset;
use App\Models\Project;
use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use App\Models\ProviderCredential;
use App\Models\Run;
use App\Models\RunStep;
use App\Services\Llm\ModelCatalog;
use App\Support\Llm\ProviderCapabilities;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ChainViewService
{
    public function __construct(
        private ModelCatalog $modelCatalog,
        private ProviderCapabilities $providerCapabilities
    ) {
    }

    public function buildShowData(Project $project, Chain $chain): array
    {
        $providerCredentials = $this->providerCredentials();
        $promptTemplates = $this->promptTemplateOptions($project);
        $versionToTemplate = $this->mapVersionToTemplate($promptTemplates);
        $contextSample = $this->buildContextSample($chain);

        $chain->load([
            'nodes' => function ($query) {
                $query->with('providerCredential:id,name,provider')->orderBy('order_index');
            },
        ]);

        /** @var \Illuminate\Database\Eloquent\Collection<int, ChainNode> $nodes */
        $nodes = $chain->nodes;

        return [
            'project' => $project->only(['id', 'name', 'description']),
            'chain' => [
                'id' => $chain->id,
                'name' => $chain->name,
                'description' => $chain->description,
            ],
            'nodes' => $this->presentNodes($nodes, $versionToTemplate),
            'providerCredentials' => $this->providerCredentialOptions($providerCredentials),
            'providerCredentialModels' => $this->providerCredentialModels($providerCredentials),
            'providerOptions' => $this->providerOptions(),
            'promptTemplates' => $promptTemplates,
            'datasets' => $this->datasetOptions($project),
            'contextSample' => $contextSample,
        ];
    }

    private function presentNodes(Collection $nodes, array $versionToTemplate): array
    {
        return $nodes->map(function (ChainNode $node) use ($versionToTemplate): array {
            /** @var array[] $messagesConfig */
            $messagesConfig = (array) $node->messages_config;

            $messages = collect($messagesConfig)
                ->map(function (array $message) use ($versionToTemplate): array {
                    if (! isset($message['prompt_template_id']) && isset($message['prompt_version_id'])) {
                        $message['prompt_template_id'] = $versionToTemplate[$message['prompt_version_id']] ?? null;
                    }

                    return $message;
                })
                ->values()
                ->all();

            /** @var ProviderCredential|null $providerCredential */
            $providerCredential = $node->providerCredential;

            return [
                'id' => $node->id,
                'name' => $node->name,
                'order_index' => $node->order_index,
                'provider_credential_id' => $node->provider_credential_id,
                'provider_credential' => $providerCredential
                    ? [
                        'id' => $providerCredential->id,
                        'name' => $providerCredential->name,
                        'provider' => $providerCredential->provider,
                    ]
                    : null,
                'model_name' => $node->model_name,
                'model_params' => $node->model_params ?? [],
                'messages_config' => $messages,
                'output_schema_definition' => $node->output_schema_definition,
                'output_schema' => $node->output_schema,
                'stop_on_validation_error' => $node->stop_on_validation_error,
            ];
        })->values()->all();
    }

    private function providerCredentialOptions(?Collection $providerCredentials = null): array
    {
        return ($providerCredentials ?? $this->providerCredentials())
            ->map(fn (ProviderCredential $credential) => [
                'value' => $credential->id,
                'label' => sprintf('%s (%s)', $credential->name, $credential->provider),
                'provider' => $credential->provider,
            ])
            ->all();
    }

    private function providerOptions(): array
    {
        return collect($this->providerCapabilities->all())
            ->map(function (array $capabilities, string $provider) {
                return [
                    'value' => $provider,
                    'label' => Str::headline($provider),
                    'supports_chat' => $capabilities['supports_chat'],
                    'supports_model_listing' => $capabilities['supports_model_listing'],
                ];
            })
            ->values()
            ->all();
    }

    private function promptTemplateOptions(Project $project): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, PromptTemplate> $templates */
        $templates = PromptTemplate::query()
            ->with(['promptVersions' => function ($query) {
                $query->orderByDesc('version');
            }])
            ->where('project_id', $project->id)
            ->orderBy('name')
            ->get(['id', 'name', 'project_id', 'variables']);

        return $templates
            ->map(function (PromptTemplate $template) {
                /** @var \Illuminate\Database\Eloquent\Collection<int, PromptVersion> $versions */
                $versions = $template->promptVersions;
                $latest = $versions->sortByDesc('version')->first();

                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'variables' => $template->variables ?? [],
                    'latest_version_id' => $latest?->id,
                    'versions' => $versions->map(function (PromptVersion $version) {
                        return [
                            'id' => $version->id,
                            'version' => $version->version,
                            'created_at' => $version->created_at,
                        ];
                    })->values(),
                ];
            })
            ->all();
    }

    private function datasetOptions(Project $project): array
    {
        return Dataset::query()
            ->where('project_id', $project->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Dataset $dataset) => [
                'value' => $dataset->id,
                'label' => $dataset->name,
            ])
            ->all();
    }

    private function providerCredentials(): Collection
    {
        return ProviderCredential::query()
            ->orderBy('name')
            ->get(['id', 'name', 'provider']);
    }

    private function providerCredentialModels(?Collection $providerCredentials = null): array
    {
        return ($providerCredentials ?? $this->providerCredentials())
            ->mapWithKeys(fn (ProviderCredential $credential) => [
                $credential->id => $this->modelCatalog->getModelsFor($credential),
            ])
            ->all();
    }

    private function buildContextSample(Chain $chain): array
    {
        $latestRun = Run::query()
            ->where('chain_id', $chain->id)
            ->where('tenant_id', currentTenantId())
            ->where('status', 'success')
            ->latest()
            ->with(['steps.chainNode'])
            ->first();

        /** @var \Illuminate\Database\Eloquent\Collection<int, ChainNode> $nodes */
        $nodes = $chain->nodes()->orderBy('order_index')->get();
        /** @var \Illuminate\Support\Collection<int, RunStep> $stepsFromRun */
        $stepsFromRun = collect($latestRun ? $latestRun->steps : []);

        $steps = $nodes->map(function (ChainNode $node) use ($stepsFromRun) {
            $stepKey = Str::slug($node->name, '_') ?: 'step_'.$node->id;

            $runStep = $stepsFromRun->firstWhere('chain_node_id', $node->id);

            $sample = $this->buildStepSample($node, $runStep);

            return [
                'key' => $stepKey,
                'name' => $node->name,
                'order_index' => $node->order_index,
                'sample' => $sample,
            ];
        });

        return [
            'input' => $latestRun?->input,
            'steps' => $steps->values()->all(),
        ];
    }

    private function sampleFromSchema(mixed $schema): mixed
    {
        if (! $schema || ! is_array($schema)) {
            return [];
        }

        $type = $schema['type'] ?? null;

        if ($type === 'object' && isset($schema['fields']) && is_array($schema['fields'])) {
            return collect($schema['fields'])
                ->map(fn ($prop) => $this->sampleFromSchema($prop))
                ->all();
        }

        if ($type === 'array' && isset($schema['items'])) {
            $item = $this->sampleFromSchema($schema['items']);

            return [$item ?: 'array_item'];
        }

        if ($type === 'enum' && isset($schema['values']) && is_array($schema['values'])) {
            return implode(' | ', $schema['values']);
        }

        return $type ?? 'string';
    }

    private function buildStepSample(ChainNode $node, ?RunStep $runStep): array
    {
        if ($runStep) {
            return $this->sampleFromRunStep($runStep);
        }

        $parsedSample = $this->sampleFromSchema($node->output_schema);

        return [
            'parsed_output' => $parsedSample ?: [],
            'raw_output' => 'string',
            'response_raw' => ['choices' => []],
        ];
    }

    private function sampleFromRunStep(RunStep $runStep): array
    {
        $parsed = $runStep->parsed_output;
        $raw = $runStep->response_raw ?? [];

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $raw = $decoded;
            }
        }

        $rawOutput = data_get($raw, 'choices.0.message.content');

        if (! $rawOutput && is_string($raw)) {
            $rawOutput = $raw;
        }

        return [
            'parsed_output' => $parsed,
            'raw_output' => $rawOutput,
            'response_raw' => $raw,
        ];
    }

    private function mapVersionToTemplate(array $promptTemplates): array
    {
        $map = [];

        foreach ($promptTemplates as $template) {
            foreach ($template['versions'] as $version) {
                $map[$version['id']] = $template['id'];
            }
        }

        return $map;
    }
}
