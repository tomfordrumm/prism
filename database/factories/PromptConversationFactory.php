<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Project;
use App\Models\PromptConversation;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromptConversation>
 */
class PromptConversationFactory extends Factory
{
    protected $model = PromptConversation::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'project_id' => Project::factory(),
            'type' => 'agent_chat',
            'run_id' => null,
            'run_step_id' => null,
            'target_prompt_version_id' => null,
            'agent_id' => Agent::factory(),
            'status' => 'active',
        ];
    }
}
