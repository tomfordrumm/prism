<?php

namespace App\Http\Controllers;

use App\Http\Requests\Agents\StoreAgentRequest;
use App\Http\Requests\Agents\UpdateAgentRequest;
use App\Models\Agent;
use App\Models\Project;
use App\Models\PromptTemplate;
use App\Models\ProviderCredential;
use App\Services\Llm\ModelCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class AgentController extends Controller
{
    public function __construct(
        private ModelCatalog $modelCatalog
    ) {}

    public function index(Project $project): Response
    {
        $this->assertProjectTenant($project);

        $agents = $project->agents()
            ->with('providerCredential')
            ->withCount('conversations')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn (Agent $agent) => [
                'id' => $agent->id,
                'name' => $agent->name,
                'description' => $agent->description,
                'model_name' => $agent->model_name,
                'is_active' => $agent->is_active,
                'last_used_at' => $agent->last_used_at?->toISOString(),
                'conversations_count' => $agent->conversations_count,
                'total_conversations' => $agent->total_conversations,
                'total_messages' => $agent->total_messages,
            ]);

        return Inertia::render('projects/agents/Index', [
            'project' => $project->only(['id', 'uuid', 'name', 'description']),
            'agents' => $agents,
        ]);
    }

    public function create(Project $project): Response
    {
        $this->assertProjectTenant($project);

        $providerCredentials = ProviderCredential::query()
            ->orderBy('name')
            ->get(['id', 'name', 'provider', 'encrypted_api_key', 'metadata']);

        $providerCredentialModels = $providerCredentials
            ->mapWithKeys(fn (ProviderCredential $credential) => [
                $credential->id => $this->modelCatalog->getModelsFor($credential),
            ])
            ->all();

        $promptTemplates = PromptTemplate::query()
            ->where('project_id', $project->id)
            ->with(['promptVersions' => function ($query) {
                $query->orderBy('version', 'desc');
            }])
            ->orderBy('name')
            ->get()
            ->map(function (PromptTemplate $template) {
                $latest = $template->promptVersions->first();

                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'variables' => $template->variables ?? [],
                    'latest_version_id' => $latest?->id,
                    'versions' => $template->promptVersions->map(function ($version) {
                        return [
                            'id' => $version->id,
                            'version' => $version->version,
                            'created_at' => $version->created_at,
                            'content' => $version->content,
                        ];
                    })->values(),
                ];
            });

        return Inertia::render('projects/agents/Create', [
            'project' => $project->only(['id', 'uuid', 'name', 'description']),
            'providerCredentials' => $providerCredentials->map(fn ($c) => [
                'value' => $c->id,
                'label' => $c->name,
                'provider' => $c->provider,
            ]),
            'providerCredentialModels' => $providerCredentialModels,
            'promptTemplates' => $promptTemplates,
        ]);
    }

    public function store(StoreAgentRequest $request, Project $project): RedirectResponse
    {
        $this->assertProjectTenant($project);

        $resolvedPrompt = $this->resolveSystemPrompt(
            project: $project,
            mode: $request->input('system_prompt_mode', 'inline'),
            templateId: $request->integer('system_prompt_template_id'),
            versionId: $request->integer('system_prompt_version_id'),
            inlineContent: $request->input('system_inline_content')
        );

        $agent = Agent::create([
            'tenant_id' => currentTenantId(),
            'project_id' => $project->id,
            'name' => $request->string('name'),
            'description' => $request->input('description'),
            'system_prompt_content' => $resolvedPrompt['content'],
            'system_prompt_mode' => $request->input('system_prompt_mode', 'inline'),
            'system_prompt_template_id' => $resolvedPrompt['template_id'],
            'system_prompt_version_id' => $resolvedPrompt['version_id'],
            'system_inline_content' => $resolvedPrompt['inline_content'],
            'provider_credential_id' => $request->integer('provider_credential_id'),
            'model_name' => $request->string('model_name'),
            'model_params' => $request->input('model_params', []),
            'max_context_messages' => $request->integer('max_context_messages', 20),
        ]);

        return redirect()->route('projects.agents.show', [$project, $agent])
            ->with('success', 'Agent created successfully');
    }

    public function show(Project $project, Agent $agent): Response
    {
        $this->assertAgentProject($agent, $project);

        $modelParams = $agent->model_params ?? [];
        $temperature = Arr::get($modelParams, 'temperature');
        $maxTokens = Arr::get($modelParams, 'max_tokens');

        // Load recent conversations for sidebar
        $conversations = $agent->conversations()
            ->withCount('messages')
            ->orderBy('updated_at', 'desc')
            ->limit(50)
            ->get(['id', 'type', 'status', 'created_at', 'updated_at'])
            ->map(fn ($conversation) => [
                'id' => $conversation->id,
                'type' => $conversation->type,
                'status' => $conversation->status,
                'title' => null,
                'message_count' => $conversation->messages_count ?? 0,
                'created_at' => $conversation->created_at?->toISOString(),
                'updated_at' => $conversation->updated_at?->toISOString(),
            ]);

        // Load 30-day analytics
        $analytics = $agent->getAnalyticsForPeriod(30);

        return Inertia::render('projects/agents/Show', [
            'project' => $project->only(['id', 'uuid', 'name', 'description']),
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'description' => $agent->description,
                'system_prompt_content' => $agent->system_prompt_content,
                'system_prompt_mode' => $agent->system_prompt_mode,
                'system_prompt_template_id' => $agent->system_prompt_template_id,
                'system_prompt_version_id' => $agent->system_prompt_version_id,
                'system_inline_content' => $agent->system_inline_content,
                'model_name' => $agent->model_name,
                'temperature' => is_numeric($temperature) ? (float) $temperature : null,
                'max_tokens' => is_numeric($maxTokens) ? (int) $maxTokens : null,
                'model_params' => $agent->model_params,
                'max_context_messages' => $agent->max_context_messages,
                'is_active' => $agent->is_active,
                'total_conversations' => $agent->total_conversations,
                'total_messages' => $agent->total_messages,
                'total_tokens_in' => $agent->total_tokens_in,
                'total_tokens_out' => $agent->total_tokens_out,
                'last_used_at' => $agent->last_used_at?->toISOString(),
                'provider_credential' => $agent->providerCredential?->only(['id', 'name', 'provider']),
            ],
            'conversations' => $conversations,
            'analytics' => $analytics,
        ]);
    }

    public function edit(Project $project, Agent $agent): Response
    {
        $this->assertAgentProject($agent, $project);

        $providerCredentials = ProviderCredential::query()
            ->orderBy('name')
            ->get(['id', 'name', 'provider', 'encrypted_api_key', 'metadata']);

        $providerCredentialModels = $providerCredentials
            ->mapWithKeys(fn (ProviderCredential $credential) => [
                $credential->id => $this->modelCatalog->getModelsFor($credential),
            ])
            ->all();

        $promptTemplates = PromptTemplate::query()
            ->where('project_id', $project->id)
            ->with(['promptVersions' => function ($query) {
                $query->orderBy('version', 'desc');
            }])
            ->orderBy('name')
            ->get()
            ->map(function (PromptTemplate $template) {
                $latest = $template->promptVersions->first();

                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'variables' => $template->variables ?? [],
                    'latest_version_id' => $latest?->id,
                    'versions' => $template->promptVersions->map(function ($version) {
                        return [
                            'id' => $version->id,
                            'version' => $version->version,
                            'created_at' => $version->created_at,
                            'content' => $version->content,
                        ];
                    })->values(),
                ];
            });

        return Inertia::render('projects/agents/Edit', [
            'project' => $project->only(['id', 'uuid', 'name', 'description']),
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'description' => $agent->description,
                'system_prompt_content' => $agent->system_prompt_content,
                'system_prompt_mode' => $agent->system_prompt_mode,
                'system_prompt_template_id' => $agent->system_prompt_template_id,
                'system_prompt_version_id' => $agent->system_prompt_version_id,
                'system_inline_content' => $agent->system_inline_content,
                'provider_credential_id' => $agent->provider_credential_id,
                'model_name' => $agent->model_name,
                'model_params' => $agent->model_params,
                'max_context_messages' => $agent->max_context_messages,
                'is_active' => $agent->is_active,
            ],
            'providerCredentials' => $providerCredentials->map(fn ($c) => [
                'value' => $c->id,
                'label' => $c->name,
                'provider' => $c->provider,
            ]),
            'providerCredentialModels' => $providerCredentialModels,
            'promptTemplates' => $promptTemplates,
        ]);
    }

    public function update(UpdateAgentRequest $request, Project $project, Agent $agent): RedirectResponse
    {
        $this->assertAgentProject($agent, $project);

        $resolvedPrompt = $this->resolveSystemPrompt(
            project: $project,
            mode: $request->input('system_prompt_mode', $agent->system_prompt_mode),
            templateId: $request->integer('system_prompt_template_id'),
            versionId: $request->integer('system_prompt_version_id'),
            inlineContent: $request->input('system_inline_content')
        );

        $agent->update([
            'name' => $request->string('name'),
            'description' => $request->input('description'),
            'system_prompt_content' => $resolvedPrompt['content'],
            'system_prompt_mode' => $request->input('system_prompt_mode', $agent->system_prompt_mode),
            'system_prompt_template_id' => $resolvedPrompt['template_id'],
            'system_prompt_version_id' => $resolvedPrompt['version_id'],
            'system_inline_content' => $resolvedPrompt['inline_content'],
            'provider_credential_id' => $request->integer('provider_credential_id'),
            'model_name' => $request->string('model_name'),
            'model_params' => $request->input('model_params', []),
            'max_context_messages' => $request->integer('max_context_messages', 20),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('projects.agents.show', [$project, $agent])
            ->with('success', 'Agent updated successfully');
    }

    public function destroy(Request $request, Project $project, Agent $agent): RedirectResponse
    {
        $this->assertAgentProject($agent, $project);

        // Validate confirmation
        $request->validate([
            'confirmation' => 'required|string|in:'.$agent->name,
        ], [
            'confirmation.in' => 'Please type the agent name exactly to confirm deletion.',
        ]);

        // Delete agent and all related conversations (cascade)
        $agent->conversations()->delete();
        $agent->delete();

        return redirect()->route('projects.agents.index', $project)
            ->with('success', 'Agent and all conversations deleted successfully');
    }

    private function assertProjectTenant(Project $project): void
    {
        if ($project->tenant_id !== currentTenantId()) {
            abort(404);
        }
    }

    private function assertAgentProject(Agent $agent, Project $project): void
    {
        $this->assertProjectTenant($project);

        if ($agent->project_id !== $project->id || $agent->tenant_id !== currentTenantId()) {
            abort(404);
        }
    }

    private function resolveSystemPrompt(
        Project $project,
        string $mode,
        ?int $templateId,
        ?int $versionId,
        ?string $inlineContent
    ): array {
        if ($mode !== 'template') {
            return [
                'content' => (string) ($inlineContent ?? ''),
                'template_id' => null,
                'version_id' => null,
                'inline_content' => $inlineContent,
            ];
        }

        $content = '';
        $resolvedVersionId = null;

        if ($templateId) {
            $template = PromptTemplate::query()
                ->where('id', $templateId)
                ->where('project_id', $project->id)
                ->where('tenant_id', currentTenantId())
                ->with(['promptVersions' => function ($query) {
                    $query->orderByDesc('version');
                }])
                ->first();

            if ($template) {
                $version = null;

                if ($versionId) {
                    $version = $template->promptVersions->firstWhere('id', $versionId);
                }

                if (! $version) {
                    $version = $template->promptVersions->first();
                }

                $content = $version?->content ?? '';
                $resolvedVersionId = $version?->id;
            }
        }

        return [
            'content' => $content,
            'template_id' => $templateId,
            'version_id' => $resolvedVersionId,
            'inline_content' => null,
        ];
    }
}
