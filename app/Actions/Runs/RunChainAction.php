<?php

namespace App\Actions\Runs;

use App\Models\Run;
use App\Services\Entitlements\Contracts\UsageMeterInterface;
use App\Services\Runs\ChainSnapshotLoader;
use App\Services\Runs\PromptVersionResolver;
use App\Services\Runs\RunStepRunner;
use Illuminate\Support\Facades\Log;

class RunChainAction
{
    public function __construct(
        private ChainSnapshotLoader $snapshotLoader,
        private PromptVersionResolver $promptVersionResolver,
        private RunStepRunner $runStepRunner,
        private UsageMeterInterface $usageMeter
    ) {}

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
                'duration_ms' => (int) $runStart->diffInMilliseconds(now(), true),
                'finished_at' => now(),
            ]);
            $this->meterTokenUsage(
                run: $run,
                totalTokensIn: (int) $stepRunResult['total_tokens_in'],
                totalTokensOut: (int) $stepRunResult['total_tokens_out'],
            );

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

    private function meterTokenUsage(Run $run, int $totalTokensIn, int $totalTokensOut): void
    {
        $context = [
            'run_id' => $run->id,
            'project_id' => $run->project_id,
            'chain_id' => $run->chain_id,
            'source' => 'run_finished',
        ];

        if ($totalTokensIn > 0) {
            $this->safeMeter(
                run: $run,
                meter: 'input_tokens',
                quantity: $totalTokensIn,
                context: $context,
            );
        }

        if ($totalTokensOut > 0) {
            $this->safeMeter(
                run: $run,
                meter: 'output_tokens',
                quantity: $totalTokensOut,
                context: $context,
            );
        }
    }

    private function safeMeter(Run $run, string $meter, int $quantity, array $context): void
    {
        try {
            $this->usageMeter->meter(
                tenantId: $run->tenant_id,
                meter: $meter,
                quantity: $quantity,
                context: $context,
            );
        } catch (\Throwable $e) {
            Log::error('RunChainAction: usage metering failed', [
                'run_id' => $run->id,
                'tenant_id' => $run->tenant_id,
                'meter' => $meter,
                'quantity' => $quantity,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
