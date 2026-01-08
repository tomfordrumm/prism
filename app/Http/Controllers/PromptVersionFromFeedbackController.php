<?php

namespace App\Http\Controllers;

use App\Http\Requests\PromptTemplates\StoreVersionFromFeedbackRequest;
use App\Models\Feedback;
use App\Models\Project;
use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use App\Support\TargetPromptResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class PromptVersionFromFeedbackController extends Controller
{
    public function __invoke(
        StoreVersionFromFeedbackRequest $request,
        Project $project,
        PromptTemplate $promptTemplate,
        TargetPromptResolver $targetPromptResolver
    ): RedirectResponse {
        $this->assertTemplateProject($promptTemplate, $project);

        $feedback = Feedback::findOrFail($request->integer('feedback_id'));

        if ($feedback->tenant_id !== currentTenantId()) {
            abort(404);
        }

        $suggested = $feedback->suggested_prompt_content;

        if (! $suggested) {
            abort(422, 'Feedback does not contain suggestion.');
        }

        $targetPromptVersionId = $feedback->target_prompt_version_id
            ?: $targetPromptResolver->fromRunStep($feedback->runStep);

        $targetVersion = $targetPromptVersionId
            ? PromptVersion::find($targetPromptVersionId)
            : null;

        if (! $targetVersion || $targetVersion->prompt_template_id !== $promptTemplate->id) {
            abort(404);
        }

        $nextVersion = (int) ($promptTemplate->promptVersions()->max('version') ?? 0) + 1;

        DB::transaction(function () use ($promptTemplate, $suggested, $request, $nextVersion): void {
            PromptVersion::create([
                'tenant_id' => currentTenantId(),
                'prompt_template_id' => $promptTemplate->id,
                'version' => $nextVersion,
                'content' => $suggested,
                'changelog' => $request->input('changelog') ?: 'Created from feedback suggestion',
                'created_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('projects.prompts.index', [
            $project,
            'prompt_id' => $promptTemplate->id,
        ]);
    }

    private function assertTemplateProject(PromptTemplate $template, Project $project): void
    {
        if ($template->project_id !== $project->id || $template->tenant_id !== $project->tenant_id) {
            abort(404);
        }
    }
}
