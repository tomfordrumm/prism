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
use Mockery\MockInterface;
use Tests\TestCase;

class RunChainActionUsageMeteringTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Project $project;

    private Run $run;

    private MockInterface $snapshotLoader;

    private MockInterface $promptVersionResolver;

    private MockInterface $runStepRunner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['name' => 'Acme']);
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Demo',
        ]);

        $this->run = Run::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'chain_id' => null,
            'input' => [],
            'chain_snapshot' => [],
            'status' => 'pending',
            'started_at' => now(),
        ]);

        $this->snapshotLoader = Mockery::mock(ChainSnapshotLoader::class);
        $this->snapshotLoader->shouldReceive('load')->once()->with($this->run)->andReturn(collect());

        $this->promptVersionResolver = Mockery::mock(PromptVersionResolver::class);
        $this->promptVersionResolver->shouldReceive('loadForNodes')->once()->andReturn([
            'by_id' => collect(),
            'by_template' => collect(),
        ]);

        $this->runStepRunner = Mockery::mock(RunStepRunner::class);
        $this->runStepRunner->shouldReceive('runSteps')->once()->andReturn([
            'total_tokens_in' => 125,
            'total_tokens_out' => 220,
            'failed' => false,
        ]);
    }

    public function test_execute_meters_input_and_output_tokens(): void
    {

        $usageMeter = Mockery::mock(UsageMeterInterface::class);
        $usageMeter->shouldReceive('meter')->once()->withArgs(function (
            int $tenantId,
            string $meter,
            int $quantity,
            array $context
        ): bool {
            return $tenantId === $this->run->tenant_id
                && $meter === 'input_tokens'
                && $quantity === 125
                && $context['run_id'] === $this->run->id;
        });
        $usageMeter->shouldReceive('meter')->once()->withArgs(function (
            int $tenantId,
            string $meter,
            int $quantity,
            array $context
        ): bool {
            return $tenantId === $this->run->tenant_id
                && $meter === 'output_tokens'
                && $quantity === 220
                && $context['run_id'] === $this->run->id;
        });

        $action = new RunChainAction(
            snapshotLoader: $this->snapshotLoader,
            promptVersionResolver: $this->promptVersionResolver,
            runStepRunner: $this->runStepRunner,
            usageMeter: $usageMeter,
        );

        $action->execute($this->run);
        $this->run->refresh();

        $this->assertSame('success', $this->run->status);
        $this->assertSame(125, $this->run->total_tokens_in);
        $this->assertSame(220, $this->run->total_tokens_out);
    }

    public function test_execute_keeps_success_status_when_metering_throws(): void
    {
        $usageMeter = Mockery::mock(UsageMeterInterface::class);
        $usageMeter->shouldReceive('meter')->twice()->andThrow(new \RuntimeException('meter failed'));

        $action = new RunChainAction(
            snapshotLoader: $this->snapshotLoader,
            promptVersionResolver: $this->promptVersionResolver,
            runStepRunner: $this->runStepRunner,
            usageMeter: $usageMeter,
        );

        $action->execute($this->run);
        $this->run->refresh();

        $this->assertSame('success', $this->run->status);
        $this->assertSame(125, $this->run->total_tokens_in);
        $this->assertSame(220, $this->run->total_tokens_out);
    }
}
