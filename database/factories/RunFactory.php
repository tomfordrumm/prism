<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Run;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Run>
 */
class RunFactory extends Factory
{
    protected $model = Run::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'project_id' => Project::factory()->state(fn (array $attributes): array => [
                'tenant_id' => $attributes['tenant_id'],
            ]),
            'chain_id' => null,
            'dataset_id' => null,
            'test_case_id' => null,
            'input' => [],
            'chain_snapshot' => [],
            'status' => 'pending',
            'error_message' => null,
            'total_tokens_in' => null,
            'total_tokens_out' => null,
            'total_cost' => null,
            'duration_ms' => null,
            'started_at' => now(),
            'finished_at' => null,
        ];
    }
}
