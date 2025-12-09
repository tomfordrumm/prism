<?php

namespace App\Actions\Runs;

use App\Models\Run;
use App\Services\Runs\ChainSnapshotLoader;
use App\Services\Runs\PromptVersionResolver;
use App\Services\Runs\RunStepRunner;
use Illuminate\Support\Facades\Log;

class RunChainAction
{
    public function __construct(
        private ChainSnapshotLoader $snapshotLoader,
        private PromptVersionResolver $promptVersionResolver,
        private RunStepRunner $runStepRunner
    ) {
    }

    public function execute(Run $run): Run
    {
        try {
            $runStart = now();
            $run->update([
                'status' => 'running',
                'started_at' => $run->started_at ?? now(),
            ]);

            /** @var \Illuminate\Support\Collection<int, \App\Models\ChainNode> $nodes */
            $nodes = $this->snapshotLoader->load($run);

            $promptVersions = $this->promptVersionResolver->loadForNodes($nodes);
            $stepRunResult = $this->runStepRunner->runSteps($run, $nodes, $promptVersions);

            $run->update([
                'status' => $stepRunResult['failed'] ? 'failed' : 'success',
                'total_tokens_in' => $stepRunResult['total_tokens_in'] ?: null,
                'total_tokens_out' => $stepRunResult['total_tokens_out'] ?: null,
                'duration_ms' => now()->diffInMilliseconds($runStart),
                'finished_at' => now(),
            ]);

            return $run;
        } catch (\Throwable $e) {
            Log::error('RunChainAction: fatal failure', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);

            $run->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            return $run;
        }
    }
}
