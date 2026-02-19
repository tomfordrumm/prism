<?php

namespace Database\Factories;

use App\Models\Chain;
use App\Models\ChainNode;
use App\Models\ProviderCredential;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChainNode>
 */
class ChainNodeFactory extends Factory
{
    protected $model = ChainNode::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'chain_id' => Chain::factory()->state(fn (array $attributes): array => [
                'tenant_id' => $attributes['tenant_id'],
            ]),
            'name' => fake()->words(2, true),
            'order_index' => 1,
            'provider_credential_id' => ProviderCredential::factory()->state(fn (array $attributes): array => [
                'tenant_id' => $attributes['tenant_id'],
            ]),
            'model_name' => 'gpt-test',
            'model_params' => [],
            'messages_config' => [],
            'output_schema' => null,
            'output_schema_definition' => null,
            'stop_on_validation_error' => false,
        ];
    }
}
