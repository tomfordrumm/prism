<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\PromptTemplate;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromptTemplate>
 */
class PromptTemplateFactory extends Factory
{
    protected $model = PromptTemplate::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'project_id' => Project::factory()->state(fn (array $attributes): array => [
                'tenant_id' => $attributes['tenant_id'],
            ]),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'variables' => [],
        ];
    }
}
