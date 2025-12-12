<?php

namespace App\Http\Controllers;

use App\Http\Requests\Feedback\StoreFeedbackRequest;
use App\Models\Feedback;
use App\Models\Run;
use App\Models\RunStep;
use App\Services\Feedback\FeedbackSubmissionService;
use App\Services\Feedback\Exceptions\FeedbackSubmissionException;
use Illuminate\Http\RedirectResponse;

class FeedbackController extends Controller
{
    public function store(
        StoreFeedbackRequest $request,
        Run $run,
        RunStep $runStep,
        FeedbackSubmissionService $submissionService
    ): RedirectResponse {
        $this->assertRunStep($run, $runStep);

        try {
            Feedback::create(
                $submissionService->submit(
                    $run,
                    $runStep,
                    $request->user()->id,
                    (string) $request->string('comment'),
                    $request->has('rating') ? $request->integer('rating') : null,
                    $request->boolean('request_suggestion'),
                    $request->has('provider_credential_id') ? $request->integer('provider_credential_id') : null,
                    (string) $request->string('model_name')
                )
            );
        } catch (FeedbackSubmissionException $exception) {
            return redirect()->back()->withErrors([
                'suggestion' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            return redirect()->back()->withErrors([
                'suggestion' => $exception->getMessage() ?: 'Failed to submit feedback.',
            ]);
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
