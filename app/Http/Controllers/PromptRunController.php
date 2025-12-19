<?php

namespace App\Http\Controllers;

use App\Actions\Prompts\RunPromptTemplateAction;
use App\Http\Requests\PromptTemplates\RunPromptTemplateRequest;
use App\Jobs\ExecuteRunJob;
use App\Models\Project;
use App\Models\PromptTemplate;
use App\Models\ProviderCredential;
use Illuminate\Http\RedirectResponse;

class PromptRunController extends Controller
{
    public function store(
        RunPromptTemplateRequest $request,
        Project $project,
        PromptTemplate $promptTemplate,
        RunPromptTemplateAction $action
    ): RedirectResponse {
        $this->assertTemplateProject($promptTemplate, $project);

        $providerCredential = ProviderCredential::query()
            ->where('tenant_id', currentTenantId())
            ->findOrFail($request->integer('provider_credential_id'));

        $variablesInput = $request->input('variables');
        $variables = $variablesInput ? json_decode($variablesInput, true) : [];

        $run = $action->run(
            $project,
            $promptTemplate,
            $providerCredential,
            $request->string('model_name'),
            is_array($variables) ? $variables : []
        );

        ExecuteRunJob::dispatch($run->id);

        return redirect()->route('projects.runs.show', [$project, $run]);
    }

    private function assertTemplateProject(PromptTemplate $template, Project $project): void
    {
        if ($template->project_id !== $project->id || $template->tenant_id !== $project->tenant_id) {
            abort(404);
        }
    }
}
