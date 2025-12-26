<?php

namespace App\Http\Controllers;

use App\Http\Requests\Projects\StoreProjectRequest;
use App\Models\Project;
use App\Models\PromptVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(): Response
    {
        $projects = Project::query()
            ->latest()
            ->get(['id', 'uuid', 'name', 'description']);

        return Inertia::render('projects/Index', [
            'projects' => $projects,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('projects/Create');
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = Project::create([
            'tenant_id' => currentTenantId(),
            'name' => $request->string('name'),
            'description' => $request->input('description'),
        ]);

        return redirect()->route('projects.show', $project);
    }

    public function show(Project $project): Response
    {
        $project->loadCount(['promptTemplates', 'chains', 'runs']);
        $lastMonthTokens = $project->runs()
            ->where('created_at', '>=', now()->subMonth())
            ->sum(DB::raw('coalesce(total_tokens_in, 0) + coalesce(total_tokens_out, 0)'));

        return Inertia::render('projects/Show', [
            'project' => $project->only([
                'id',
                'uuid',
                'name',
                'description',
                'prompt_templates_count',
                'chains_count',
                'runs_count',
            ]),
            'lastMonthTokens' => (int) $lastMonthTokens,
            'recentActivity' => $this->recentActivity($project),
        ]);
    }

    private function recentActivity(Project $project): array
    {
        $runs = $project->runs()
            ->latest()
            ->take(5)
            ->get(['id', 'chain_id', 'status', 'created_at']);
        $runs->load('chain:id,name');

        $promptVersions = PromptVersion::query()
            ->whereHas('promptTemplate', fn ($query) => $query->where('project_id', $project->id))
            ->with('promptTemplate:id,project_id,name')
            ->latest('created_at')
            ->take(5)
            ->get(['id', 'prompt_template_id', 'version', 'created_at']);

        $datasets = $project->datasets()
            ->latest()
            ->take(5)
            ->get(['id', 'name', 'created_at']);

        $chains = $project->chains()
            ->latest()
            ->take(5)
            ->get(['id', 'name', 'created_at']);

        $activity = collect();

        foreach ($runs as $run) {
            $activity->push([
                'id' => 'run-'.$run->id,
                'type' => 'run',
                'title' => $this->runActivityTitle($run->status),
                'description' => $run->chain?->name ? "Chain: {$run->chain->name}" : 'Prompt run',
                'timestamp' => $this->formatTimestamp($run->created_at),
                'href' => route('projects.runs.show', [$project, $run]),
                'status' => $run->status,
            ]);
        }

        foreach ($promptVersions as $version) {
            if (! $version->promptTemplate) {
                continue;
            }

            $activity->push([
                'id' => 'prompt-'.$version->id,
                'type' => 'prompt',
                'title' => 'Prompt updated',
                'description' => sprintf('%s v%s', $version->promptTemplate->name, $version->version),
                'timestamp' => $this->formatTimestamp($version->created_at),
                'href' => route('projects.prompts.show', [$project, $version->promptTemplate]),
            ]);
        }

        foreach ($datasets as $dataset) {
            $activity->push([
                'id' => 'dataset-'.$dataset->id,
                'type' => 'dataset',
                'title' => 'Dataset created',
                'description' => $dataset->name,
                'timestamp' => $this->formatTimestamp($dataset->created_at),
                'href' => route('projects.datasets.show', [$project, $dataset]),
            ]);
        }

        foreach ($chains as $chain) {
            $activity->push([
                'id' => 'chain-'.$chain->id,
                'type' => 'chain',
                'title' => 'Chain created',
                'description' => $chain->name,
                'timestamp' => $this->formatTimestamp($chain->created_at),
                'href' => route('projects.chains.show', [$project, $chain]),
            ]);
        }

        return $activity
            ->sortByDesc('timestamp')
            ->take(8)
            ->values()
            ->all();
    }

    private function formatTimestamp(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        return Carbon::parse($value)->format(DATE_ATOM);
    }

    private function runActivityTitle(string $status): string
    {
        return match ($status) {
            'pending' => 'Run queued',
            'running' => 'Run running',
            'success' => 'Run completed',
            'failed' => 'Run failed',
            default => 'Run updated',
        };
    }
}
