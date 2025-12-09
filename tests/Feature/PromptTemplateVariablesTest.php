<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\PromptTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptTemplateVariablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_variables_are_synced_from_prompt_content(): void
    {
        $tenant = Tenant::create(['name' => 'Acme']);
        $project = Project::create([
            'tenant_id' => $tenant->id,
            'name' => 'Demo',
            'description' => null,
        ]);
        $user = User::factory()->create();

        $template = PromptTemplate::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'welcome_message',
            'description' => null,
            'variables' => [
                ['name' => 'topic', 'type' => 'string', 'description' => 'Existing description'],
                ['name' => 'deprecated', 'type' => 'number'],
            ],
        ]);

        $template->createNewVersion([
            'content' => 'Write about {{ topic }} for {{ audience.name }} with seed {{seed}}',
            'changelog' => 'Initial',
            'created_by' => $user->id,
        ]);

        $template->refresh();

        $this->assertSame([
            ['name' => 'topic', 'type' => 'string', 'description' => 'Existing description'],
            ['name' => 'audience.name'],
            ['name' => 'seed'],
        ], $template->variables);
    }
}
