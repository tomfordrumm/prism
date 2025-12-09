<?php

namespace App\Http\Controllers;

use App\Http\Requests\Chains\StoreChainRequest;
use App\Http\Requests\Chains\UpdateChainRequest;
use App\Models\Chain;
use App\Models\ChainNode;
use App\Models\ProviderCredential;
use App\Models\Project;
use App\Models\PromptVersion;
use App\Models\PromptTemplate;
use App\Models\Dataset;
use App\Models\Run;
use App\Models\RunStep;
use App\Services\Llm\ModelCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ChainController extends Controller
{
    public function __construct(private ModelCatalog $modelCatalog)
    {
    }

    public function index(Project $project): Response
    {
        $this->assertProjectTenant($project);

        $chains = Chain::query()
            ->withCount('nodes')
            ->where('project_id', $project->id)
            ->orderBy('name')
            ->get()
            ->map(fn (Chain $chain) => [
                'id' => $chain->id,
                'name' => $chain->name,
                'description' => $chain->description,
                'nodes_count' => $chain->nodes_count,
            ]);

        return Inertia::render('projects/chains/Index', [
            'project' => $project->only(['id', 'name', 'description']),
            'chains' => $chains,
        ]);
    }

    public function create(Project $project): Response
    {
        $this->assertProjectTenant($project);

        return Inertia::render('projects/chains/Create', [
            'project' => $project->only(['id', 'name', 'description']),
        ]);
    }

    public function store(StoreChainRequest $request, Project $project): RedirectResponse
    {
        $this->assertProjectTenant($project);

        $chain = Chain::create([
            'tenant_id' => currentTenantId(),
            'project_id' => $project->id,
            'name' => $request->string('name'),
            'description' => $request->input('description'),
        ]);

        return redirect()->route('projects.chains.show', [$project, $chain]);
    }

    public function show(Project $project, Chain $chain): Response
    {
        $this->assertChainProject($chain, $project);

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

        return Inertia::render('projects/chains/Show', [
            'project' => $project->only(['id', 'name', 'description']),
            'chain' => [
                'id' => $chain->id,
                'name' => $chain->name,
                'description' => $chain->description,
            ],
            'nodes' => $nodes->map(function (ChainNode $node) use ($versionToTemplate): array {
                /** @var array[] $messagesConfig = (array) $node->messages_config */
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

                /** @var ProviderCredential|null $providerCredential = $node->providerCredential */
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
            }),
            'providerCredentials' => $this->providerCredentialOptions($providerCredentials),
            'providerCredentialModels' => $this->providerCredentialModels($providerCredentials),
            'providerOptions' => $this->providerOptions(),
            'promptTemplates' => $promptTemplates,
            'datasets' => $this->datasetOptions($project),
            'contextSample' => $contextSample,
        ]);
    }

    public function update(UpdateChainRequest $request, Project $project, Chain $chain): RedirectResponse
    {
        $this->assertChainProject($chain, $project);

        $chain->update([
            'name' => $request->string('name'),
            'description' => $request->input('description'),
        ]);

        return redirect()->route('projects.chains.show', [$project, $chain]);
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
        return [
            ['value' => 'openai', 'label' => 'OpenAI'],
            ['value' => 'anthropic', 'label' => 'Anthropic'],
            ['value' => 'google', 'label' => 'Google'],
        ];
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
                /** @var \Illuminate\Database\Eloquent\Collection<int, PromptVersion> $versions = $template->promptVersions */
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

    private function assertProjectTenant(Project $project): void
    {
        if ($project->tenant_id !== currentTenantId()) {
            abort(404);
        }
    }

    private function assertChainProject(Chain $chain, Project $project): void
    {
        $this->assertProjectTenant($project);

        if ($chain->project_id !== $project->id || $chain->tenant_id !== $project->tenant_id) {
            abort(404);
        }
    }
}
