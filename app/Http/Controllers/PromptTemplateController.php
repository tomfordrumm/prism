<?php

namespace App\Http\Controllers;

use App\Http\Requests\PromptTemplates\StorePromptTemplateRequest;
use App\Http\Requests\PromptTemplates\StorePromptVersionRequest;
use App\Http\Requests\PromptTemplates\UpdatePromptTemplateRequest;
use App\Models\Feedback;
use App\Models\ProviderCredential;
use App\Models\Project;
use App\Models\PromptVersion;
use App\Models\PromptTemplate;
use App\Models\Dataset;
use App\Services\Llm\ModelCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class PromptTemplateController extends Controller
{
    public function __construct(private ModelCatalog $modelCatalog)
    {
    }

    public function index(Project $project): Response
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, PromptTemplate> $templates */
        $templates = PromptTemplate::query()
            ->with('latestVersion')
            ->where('project_id', $project->id)
            ->orderByDesc('updated_at')
            ->orderBy('name')
            ->get();

        $providerCredentials = $this->providerCredentials();
        $datasets = Dataset::query()
            ->where('project_id', $project->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $templateList = $templates->map(function (PromptTemplate $template): array {
            /** @var PromptVersion|null $latestVersion = $template->latestVersion */
            $latestVersion = $template->latestVersion;
            return [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'latest_version' => $latestVersion?->version,
            ];
        });

        $selectedId = request()->integer('prompt_id') ?: $templates->first()?->id;

        $selectedTemplate = null;
        $versions = collect();
        $selectedVersion = null;

        if ($selectedId) {
            $selectedTemplate = $templates->firstWhere('id', $selectedId)
                ?? PromptTemplate::query()
                    ->where('project_id', $project->id)
                    ->where('id', $selectedId)
                    ->first();
        }

        if (! $selectedTemplate && $templates->isNotEmpty()) {
            $selectedTemplate = $templates->first();
            $selectedId = $selectedTemplate->id;
        }

        if ($selectedTemplate) {
            $selectedTemplate->load(['promptVersions' => function ($query) {
                $query->orderByDesc('version');
            }]);

            /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\PromptVersion> $versions */
            $versions = $selectedTemplate->promptVersions;
            $requestedVersion = request()->integer('version');
            $selectedVersion = $versions
                ->firstWhere('version', $requestedVersion)
                ?? $versions->first();
        }

        $ratingSummary = $this->promptVersionRatings($versions->pluck('id')->all());

        return Inertia::render('projects/prompts/Index', [
            'project' => $project->only(['id', 'uuid', 'name', 'description']),
            'templates' => $templateList,
            'selectedTemplate' => $selectedTemplate ? [
                'id' => $selectedTemplate->id,
                'name' => $selectedTemplate->name,
                'description' => $selectedTemplate->description,
                'variables' => $selectedTemplate->variables,
            ] : null,
            'versions' => $versions->map(function (PromptVersion $version) use ($ratingSummary): array {
                return [
                    'id' => $version->id,
                    'version' => $version->version,
                    'changelog' => $version->changelog,
                    'created_at' => $version->created_at,
                    'content' => $version->content,
                    'rating' => $ratingSummary[$version->id] ?? ['up' => 0, 'down' => 0, 'score' => 0],
                ];
            }),
            'selectedVersion' => $selectedVersion
                ? [
                    'id' => $selectedVersion->id,
                    'version' => $selectedVersion->version,
                    'changelog' => $selectedVersion->changelog,
                    'created_at' => $selectedVersion->created_at,
                    'content' => $selectedVersion->content,
                    'rating' => $ratingSummary[$selectedVersion->id] ?? ['up' => 0, 'down' => 0, 'score' => 0],
                ]
                : null,
            'providerCredentials' => $this->providerCredentialOptions($providerCredentials),
            'providerCredentialModels' => $this->providerCredentialModels($providerCredentials),
            'datasets' => $datasets->map(fn (Dataset $dataset) => [
                'value' => $dataset->id,
                'label' => $dataset->name,
            ])->all(),
        ]);
    }

    public function create(Project $project): RedirectResponse
    {
        return redirect()->route('projects.prompts.index', [$project, 'prompt_id' => $template->id]);
    }

    public function store(StorePromptTemplateRequest $request, Project $project): RedirectResponse
    {
        $this->assertProjectTenant($project);

        $template = PromptTemplate::create([
            'tenant_id' => currentTenantId(),
            'project_id' => $project->id,
            'name' => $request->string('name'),
            'description' => $request->input('description'),
        ]);

        $template->createNewVersion([
            'content' => $request->string('initial_content'),
            'changelog' => $request->input('initial_changelog') ?? 'Initial version',
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('projects.prompts.index', [$project, 'prompt_id' => $template->id]);
    }

    public function update(
        UpdatePromptTemplateRequest $request,
        Project $project,
        PromptTemplate $promptTemplate
    ): RedirectResponse {
        $this->assertProjectTenant($project);
        $this->assertTemplateProject($promptTemplate, $project);

        $promptTemplate->update([
            'name' => $request->string('name'),
            'description' => $request->input('description'),
        ]);

        return redirect()->route('projects.prompts.index', [$project, 'prompt_id' => $promptTemplate->id]);
    }

    public function show(Project $project, PromptTemplate $promptTemplate): RedirectResponse
    {
        $this->assertProjectTenant($project);
        $this->assertTemplateProject($promptTemplate, $project);

        $requestedVersion = request()->integer('version');
        $params = [
            'prompt_id' => $promptTemplate->id,
        ];
        if ($requestedVersion) {
            $params['version'] = $requestedVersion;
        }

        return redirect()->route('projects.prompts.index', [$project, $params]);
    }

    public function storeVersion(
        StorePromptVersionRequest $request,
        Project $project,
        PromptTemplate $promptTemplate
    ): RedirectResponse {
        $this->assertProjectTenant($project);
        $this->assertTemplateProject($promptTemplate, $project);

        $promptTemplate->createNewVersion([
            'content' => $request->string('content'),
            'changelog' => $request->input('changelog'),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('projects.prompts.index', [
            $project,
            'prompt_id' => $promptTemplate->id,
            'version' => $promptTemplate->promptVersions()->max('version'),
        ]);
    }

    private function assertProjectTenant(Project $project): void
    {
        if ($project->tenant_id !== currentTenantId()) {
            abort(404);
        }
    }

    private function assertTemplateProject(PromptTemplate $template, Project $project): void
    {
        if ($template->project_id !== $project->id || $template->tenant_id !== $project->tenant_id) {
            abort(404);
        }
    }

    /**
     * @param array<int, int> $versionIds
     * @return array<int, array{up:int, down:int, score:int}>
     */
    private function promptVersionRatings(array $versionIds): array
    {
        if (! $versionIds) {
            return [];
        }

        return Feedback::query()
            ->where('tenant_id', currentTenantId())
            ->whereIn('target_prompt_version_id', $versionIds)
            ->whereNotNull('rating')
            ->selectRaw(
                'target_prompt_version_id,
                SUM(CASE WHEN rating > 0 THEN 1 ELSE 0 END) AS up,
                SUM(CASE WHEN rating < 0 THEN 1 ELSE 0 END) AS down'
            )
            ->groupBy('target_prompt_version_id')
            ->get()
            ->mapWithKeys(function ($row) {
                $up = (int) ($row->up ?? 0);
                $down = (int) ($row->down ?? 0);

                return [
                    (int) $row->target_prompt_version_id => [
                        'up' => $up,
                        'down' => $down,
                        'score' => $up - $down,
                    ],
                ];
            })
            ->all();
    }

    private function providerCredentials(): Collection
    {
        return ProviderCredential::query()
            ->where('tenant_id', currentTenantId())
            ->orderBy('name')
            ->get(['id', 'name', 'provider', 'encrypted_api_key', 'metadata']);
    }

    private function providerCredentialOptions(Collection $providerCredentials): array
    {
        return $providerCredentials
            ->map(fn (ProviderCredential $credential) => [
                'value' => $credential->id,
                'label' => sprintf('%s (%s)', $credential->name, $credential->provider),
                'provider' => $credential->provider,
            ])
            ->all();
    }

    private function providerCredentialModels(Collection $providerCredentials): array
    {
        return $providerCredentials
            ->mapWithKeys(fn (ProviderCredential $credential) => [
                $credential->id => $this->modelCatalog->getModelsFor($credential),
            ])
            ->all();
    }
}
