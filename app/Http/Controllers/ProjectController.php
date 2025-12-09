<?php

namespace App\Http\Controllers;

use App\Http\Requests\Projects\StoreProjectRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(): Response
    {
        $projects = Project::query()
            ->latest()
            ->get(['id', 'name', 'description']);

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
        return Inertia::render('projects/Show', [
            'project' => $project->only(['id', 'name', 'description']),
        ]);
    }
}
