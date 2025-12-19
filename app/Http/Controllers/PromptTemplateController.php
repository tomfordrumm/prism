<?php

namespace App\Http\Controllers;

use App\Http\Requests\PromptTemplates\StorePromptTemplateRequest;
use App\Http\Requests\PromptTemplates\StorePromptVersionRequest;
use App\Models\ProviderCredential;
use App\Models\Project;
use App\Models\PromptVersion;
use App\Models\PromptTemplate;
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

        return Inertia::render('projects/prompts/Index', [
            'project' => $project->only(['id', 'name', 'description']),
            'templates' => $templateList,
            'selectedTemplate' => $selectedTemplate ? [
                'id' => $selectedTemplate->id,
                'name' => $selectedTemplate->name,
                'description' => $selectedTemplate->description,
                'variables' => $selectedTemplate->variables,
            ] : null,
            'versions' => $versions->map(function (PromptVersion $version): array {
                return [
                    'id' => $version->id,
                    'version' => $version->version,
                    'changelog' => $version->changelog,
                    'created_at' => $version->created_at,
                    'content' => $version->content,
                ];
            }),
            'selectedVersion' => $selectedVersion
                ? [
                    'id' => $selectedVersion->id,
                    'version' => $selectedVersion->version,
                    'changelog' => $selectedVersion->changelog,
                    'created_at' => $selectedVersion->created_at,
                    'content' => $selectedVersion->content,
                ]
                : null,
            'providerCredentials' => $this->providerCredentialOptions($providerCredentials),
            'providerCredentialModels' => $this->providerCredentialModels($providerCredentials),
        ]);
    }

    public function create(Project $project): Response
    {
        return Inertia::render('projects/prompts/Create', [
            'project' => $project->only(['id', 'name', 'description']),
        ]);
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

    public function show(Project $project, PromptTemplate $promptTemplate): Response
    {
        $this->assertProjectTenant($project);
        $this->assertTemplateProject($promptTemplate, $project);

        $promptTemplate->load(['promptVersions' => function ($query) {
            $query->orderByDesc('version');
        }]);

        /** @var \Illuminate\Database\Eloquent\Collection<int, PromptVersion> $promptVersions */
        $promptVersions = $promptTemplate->promptVersions;

        $requestedVersion = request()->integer('version');
        /** @var PromptVersion|null $selectedVersion */
        $selectedVersion = $promptVersions
            ->firstWhere('version', $requestedVersion)
            ?? $promptVersions->first();

        $providerCredentials = $this->providerCredentials();

        return Inertia::render('projects/prompts/Show', [
            'project' => $project->only(['id', 'name', 'description']),
            'template' => [
                'id' => $promptTemplate->id,
                'name' => $promptTemplate->name,
                'description' => $promptTemplate->description,
                'variables' => $promptTemplate->variables,
            ],
            'versions' => $promptVersions->map(function (PromptVersion $version): array {
                return [
                    'id' => $version->id,
                    'version' => $version->version,
                    'changelog' => $version->changelog,
                    'created_at' => $version->created_at,
                    'content' => $version->content,
                ];
            }),
            'selectedVersion' => $selectedVersion
                ? [
                    'id' => $selectedVersion->id,
                    'version' => $selectedVersion->version,
                    'changelog' => $selectedVersion->changelog,
                    'created_at' => $selectedVersion->created_at,
                    'content' => $selectedVersion->content,
                ]
                : null,
            'providerCredentials' => $this->providerCredentialOptions($providerCredentials),
            'providerCredentialModels' => $this->providerCredentialModels($providerCredentials),
        ]);
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

    private function providerCredentials(): Collection
    {
        return ProviderCredential::query()
            ->where('tenant_id', currentTenantId())
            ->orderBy('name')
            ->get(['id', 'name', 'provider']);
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
