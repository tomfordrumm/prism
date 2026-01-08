<?php

namespace App\Http\Controllers;

use App\Actions\Prompts\RunPromptTemplateAction;
use App\Http\Requests\PromptTemplates\RunPromptTemplateRequest;
use App\Http\Requests\PromptTemplates\RunPromptTemplateDatasetRequest;
use App\Jobs\ExecuteRunJob;
use App\Models\Dataset;
use App\Models\Project;
use App\Models\PromptTemplate;
use App\Models\ProviderCredential;
use App\Models\TestCase;
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

    public function runDataset(
        RunPromptTemplateDatasetRequest $request,
        Project $project,
        PromptTemplate $promptTemplate,
        RunPromptTemplateAction $action
    ): RedirectResponse {
        $this->assertTemplateProject($promptTemplate, $project);

        $providerCredential = ProviderCredential::query()
            ->where('tenant_id', currentTenantId())
            ->findOrFail($request->integer('provider_credential_id'));

        $dataset = Dataset::query()
            ->where('tenant_id', currentTenantId())
            ->where('project_id', $project->id)
            ->findOrFail($request->integer('dataset_id'));

        $dataset->load('testCases');

        foreach ($dataset->testCases as $testCase) {
            /** @var TestCase $testCase */
            $variables = $testCase->input_variables ?? [];

            $run = $action->run(
                $project,
                $promptTemplate,
                $providerCredential,
                $request->string('model_name'),
                is_array($variables) ? $variables : [],
                $dataset->id,
                $testCase->id
            );

            ExecuteRunJob::dispatch($run->id);
        }

        return redirect()->route('projects.runs.index', $project);
    }

    private function assertTemplateProject(PromptTemplate $template, Project $project): void
    {
        if ($template->project_id !== $project->id || $template->tenant_id !== $project->tenant_id) {
            abort(404);
        }
    }
}
