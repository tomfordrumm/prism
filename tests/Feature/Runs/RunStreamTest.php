<?php

namespace Tests\Feature\Runs;

use App\Models\Chain;
use App\Models\ChainNode;
use App\Models\Project;
use App\Models\ProviderCredential;
use App\Models\Run;
use App\Models\RunStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunStreamTest extends TestCase
{
    use RefreshDatabase;

    public function test_stream_returns_run_updates_for_owner(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $project = Project::create([
            'tenant_id' => currentTenantId(),
            'name' => 'Demo Project',
        ]);

        $providerCredential = ProviderCredential::create([
            'tenant_id' => currentTenantId(),
            'provider' => 'openai',
            'name' => 'Sandbox Key',
            'encrypted_api_key' => 'encrypted',
        ]);

        $chain = Chain::create([
            'tenant_id' => currentTenantId(),
            'project_id' => $project->id,
            'name' => 'Test Chain',
        ]);

        $chainNode = ChainNode::create([
            'tenant_id' => currentTenantId(),
            'chain_id' => $chain->id,
            'name' => 'Step 1',
            'order_index' => 1,
            'provider_credential_id' => $providerCredential->id,
            'model_name' => 'gpt-4',
            'messages_config' => [],
            'model_params' => [],
            'output_schema' => null,
            'stop_on_validation_error' => false,
        ]);

        $run = Run::create([
            'tenant_id' => currentTenantId(),
            'project_id' => $project->id,
            'chain_id' => $chain->id,
            'input' => [],
            'chain_snapshot' => [],
            'status' => 'success',
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        RunStep::create([
            'tenant_id' => currentTenantId(),
            'run_id' => $run->id,
            'chain_node_id' => $chainNode->id,
            'order_index' => 1,
            'request_payload' => ['messages' => []],
            'response_raw' => ['content' => 'ok'],
            'parsed_output' => null,
            'tokens_in' => 10,
            'tokens_out' => 20,
            'duration_ms' => 123,
            'validation_errors' => [],
            'status' => 'success',
        ]);

        $response = $this->get("/projects/{$project->id}/runs/{$run->id}/stream");

        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('"status":"success"', $content);
        $this->assertStringContainsString('"id":'.$run->id, $content);
    }
}
