<?php

namespace Tests\Unit\Actions\Runs;

use App\Actions\Runs\RunChainAction;
use App\Models\Project;
use App\Models\Run;
use App\Models\Tenant;
use App\Services\Entitlements\Contracts\UsageMeterInterface;
use App\Services\Runs\ChainSnapshotLoader;
use App\Services\Runs\PromptVersionResolver;
use App\Services\Runs\RunStepRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RunChainActionUsageMeteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_meters_input_and_output_tokens(): void
    {
        $tenant = Tenant::create(['name' => 'Acme']);
        $project = Project::create([
            'tenant_id' => $tenant->id,
            'name' => 'Demo',
        ]);

        $run = Run::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'chain_id' => null,
            'input' => [],
            'chain_snapshot' => [],
            'status' => 'pending',
            'started_at' => now(),
        ]);

        $snapshotLoader = Mockery::mock(ChainSnapshotLoader::class);
        $snapshotLoader->shouldReceive('load')->once()->with($run)->andReturn(collect());

        $promptVersionResolver = Mockery::mock(PromptVersionResolver::class);
        $promptVersionResolver->shouldReceive('loadForNodes')->once()->andReturn([
            'by_id' => collect(),
            'by_template' => collect(),
        ]);

        $runStepRunner = Mockery::mock(RunStepRunner::class);
        $runStepRunner->shouldReceive('runSteps')->once()->andReturn([
            'total_tokens_in' => 125,
            'total_tokens_out' => 220,
            'failed' => false,
        ]);

        $usageMeter = Mockery::mock(UsageMeterInterface::class);
        $usageMeter->shouldReceive('meter')->once()->withArgs(function (
            int $tenantId,
            string $meter,
            int $quantity,
            array $context
        ) use ($run): bool {
            return $tenantId === $run->tenant_id
                && $meter === 'input_tokens'
                && $quantity === 125
                && $context['run_id'] === $run->id;
        });
        $usageMeter->shouldReceive('meter')->once()->withArgs(function (
            int $tenantId,
            string $meter,
            int $quantity,
            array $context
        ) use ($run): bool {
            return $tenantId === $run->tenant_id
                && $meter === 'output_tokens'
                && $quantity === 220
                && $context['run_id'] === $run->id;
        });

        $action = new RunChainAction(
            snapshotLoader: $snapshotLoader,
            promptVersionResolver: $promptVersionResolver,
            runStepRunner: $runStepRunner,
            usageMeter: $usageMeter,
        );

        $action->execute($run);
        $run->refresh();

        $this->assertSame('success', $run->status);
        $this->assertSame(125, $run->total_tokens_in);
        $this->assertSame(220, $run->total_tokens_out);
    }
}
