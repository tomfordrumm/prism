<?php

namespace App\Http\Controllers;

use App\Http\Requests\Chains\StoreChainRequest;
use App\Http\Requests\Chains\UpdateChainRequest;
use App\Models\Chain;
use App\Models\Project;
use App\Services\Chains\ChainViewService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ChainController extends Controller
{
    public function __construct(
        private ChainViewService $chainViewService
    ) {
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

        $viewData = $this->chainViewService->buildShowData($project, $chain);

        return Inertia::render('projects/chains/Show', $viewData);
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
