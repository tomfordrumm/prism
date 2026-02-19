<?php

namespace Database\Factories;

use App\Models\Chain;
use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chain>
 */
class ChainFactory extends Factory
{
    protected $model = Chain::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'project_id' => Project::factory()->state(fn (array $attributes): array => [
                'tenant_id' => $attributes['tenant_id'],
            ]),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'is_active' => true,
            'is_quick_prompt' => false,
        ];
    }
}
