<?php

namespace App\Http\Controllers;

use App\Http\Requests\Feedback\StoreFeedbackRequest;
use App\Models\Feedback;
use App\Models\Run;
use App\Models\RunStep;
use App\Models\PromptVersion;
use App\Services\Feedback\PromptImproverService;
use App\Support\TargetPromptResolver;
use Illuminate\Http\RedirectResponse;

class FeedbackController extends Controller
{
    public function store(
        StoreFeedbackRequest $request,
        Run $run,
        RunStep $runStep,
        PromptImproverService $improverService,
        TargetPromptResolver $targetPromptResolver
    ): RedirectResponse {
        $this->assertRunStep($run, $runStep);

        $runStep->load('chainNode');

        $targetPromptVersionId = $targetPromptResolver->fromRunStep($runStep);

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
}
