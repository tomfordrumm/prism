<?php

namespace App\Http\Controllers;

use App\Http\Requests\Projects\ImprovePromptIdeaRequest;
use App\Models\Project;
use App\Services\Prompts\PromptIdeaImproverService;
use Illuminate\Http\RedirectResponse;

class ProjectPromptIdeaController extends Controller
{
    public function __invoke(
        ImprovePromptIdeaRequest $request,
        Project $project,
        PromptIdeaImproverService $promptIdeaImprover
    ): RedirectResponse {
        if ($project->tenant_id !== currentTenantId()) {
            abort(404);
        }

        $idea = trim((string) $request->string('idea'));

        $suggestion = $promptIdeaImprover->suggest($idea);

        if (! $suggestion) {
            return redirect()
                ->route('projects.show', $project)
                ->withErrors(['idea' => 'Failed to generate a prompt suggestion.']);
        }

        return redirect()
            ->route('projects.show', $project)
            ->with([
                'prompt_idea_suggestion' => $suggestion,
                'prompt_idea_input' => $idea,
            ]);
    }
}
