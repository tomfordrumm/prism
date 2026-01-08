<?php

namespace App\Http\Controllers;

use App\Http\Requests\Feedback\StoreFeedbackRequest;
use App\Models\Feedback;
use App\Models\Run;
use App\Models\RunStep;
use App\Services\Feedback\FeedbackSubmissionService;
use App\Services\Feedback\Exceptions\FeedbackSubmissionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class FeedbackController extends Controller
{
    public function store(
        StoreFeedbackRequest $request,
        Run $run,
        RunStep $runStep,
        FeedbackSubmissionService $submissionService
    ): RedirectResponse|JsonResponse {
        $this->assertRunStep($run, $runStep);

        try {
            $feedback = Feedback::create(
                $submissionService->submit(
                    $run,
                    $runStep,
                    $request->user()->id,
                    (string) $request->string('comment'),
                    $request->has('rating') ? $request->integer('rating') : null,
                    $request->boolean('request_suggestion'),
                    $request->has('provider_credential_id') ? $request->integer('provider_credential_id') : null,
                    (string) $request->string('model_name'),
                    $request->has('target_prompt_version_id') ? $request->integer('target_prompt_version_id') : null
                )
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'feedback' => $this->presentFeedback($feedback),
                ]);
            }
        } catch (FeedbackSubmissionException $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                ], 422);
            }
            return redirect()->back()->withErrors([
                'suggestion' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage() ?: 'Failed to submit feedback.',
                ], 422);
            }
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

    private function presentFeedback(Feedback $feedback): array
    {
        return [
            'id' => $feedback->id,
            'type' => $feedback->type,
            'rating' => $feedback->rating,
            'comment' => $feedback->comment,
            'suggested_prompt_content' => $feedback->suggested_prompt_content,
            'analysis' => $feedback->analysis,
            'target_prompt_version_id' => $feedback->target_prompt_version_id,
        ];
    }
}
