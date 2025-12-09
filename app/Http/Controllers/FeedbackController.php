<?php

namespace App\Http\Controllers;

use App\Http\Requests\Feedback\StoreFeedbackRequest;
use App\Models\Feedback;
use App\Models\Run;
use App\Models\RunStep;
use App\Models\PromptVersion;
use App\Services\Feedback\PromptImproverService;
use Illuminate\Http\RedirectResponse;

class FeedbackController extends Controller
{
    public function store(
        StoreFeedbackRequest $request,
        Run $run,
        RunStep $runStep,
        PromptImproverService $improverService
    ): RedirectResponse {
        $this->assertRunStep($run, $runStep);

        $runStep->load('chainNode');

        $targetPromptVersionId = $this->targetPromptVersionId($runStep);

        $feedback = Feedback::create([
            'tenant_id' => currentTenantId(),
            'run_id' => $run->id,
            'run_step_id' => $runStep->id,
            'user_id' => $request->user()->id,
            'type' => $request->boolean('request_suggestion') ? 'llm_suggestion' : 'manual',
            'rating' => $request->input('rating'),
            'comment' => $request->string('comment'),
        ]);

        if ($request->boolean('request_suggestion') && $targetPromptVersionId) {
            $promptVersion = PromptVersion::find($targetPromptVersionId);

            if ($promptVersion && $promptVersion->tenant_id === currentTenantId()) {
                $suggestion = $improverService->suggest($runStep, $promptVersion, $feedback->comment);

                if ($suggestion) {
                    $feedback->suggested_prompt_content = $suggestion;
                    $feedback->save();
                }
            }
        }

        return redirect()->back();
    }

    private function assertRunStep(Run $run, RunStep $step): void
    {
        if ($run->tenant_id !== currentTenantId() || $step->tenant_id !== currentTenantId()) {
            abort(404);
        }

        if ($step->run_id !== $run->id) {
            abort(404);
        }
    }

    private function targetPromptVersionId(RunStep $runStep): ?int
    {
        $config = $runStep->chainNode->messages_config ?? [];

        $system = collect($config)->firstWhere('role', 'system');
        if ($system && isset($system['prompt_version_id'])) {
            return (int) $system['prompt_version_id'];
        }

        $user = collect($config)->firstWhere('role', 'user');
        if ($user && isset($user['prompt_version_id'])) {
            return (int) $user['prompt_version_id'];
        }

        return null;
    }
}
