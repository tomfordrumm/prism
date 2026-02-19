<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Project;
use App\Models\ProviderCredential;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
{
    protected $model = Agent::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'project_id' => Project::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'system_prompt_content' => 'You are a helpful assistant.',
            'system_prompt_mode' => 'inline',
            'system_prompt_template_id' => null,
            'system_prompt_version_id' => null,
            'system_inline_content' => 'You are a helpful assistant.',
            'provider_credential_id' => ProviderCredential::factory()->state([
                'provider' => 'openai',
            ]),
            'model_name' => 'gpt-4o-mini',
            'model_params' => ['temperature' => 0.7],
            'max_context_messages' => 20,
            'tool_config' => null,
            'is_active' => true,
            'last_used_at' => null,
            'total_conversations' => 0,
            'total_messages' => 0,
            'total_tokens_in' => 0,
            'total_tokens_out' => 0,
        ];
    }
}
