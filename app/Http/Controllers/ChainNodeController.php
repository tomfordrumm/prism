<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChainNodes\StoreChainNodeRequest;
use App\Http\Requests\ChainNodes\UpdateChainNodeRequest;
use App\Models\Chain;
use App\Models\ChainNode;
use App\Models\Project;
use App\Services\Chains\ChainNodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ChainNodeController extends Controller
{
    public function __construct(private ChainNodeService $chainNodeService)
    {
    }

    public function store(StoreChainNodeRequest $request, Project $project, Chain $chain): RedirectResponse
    {
        $this->assertChainProject($chain, $project);

        $messagesConfig = $this->chainNodeService->normalizeMessagesConfig($request->input('messages_config'));

        if (empty($messagesConfig)) {
            throw ValidationException::withMessages([
                'messages_config' => ['At least one prompt template is required.'],
            ]);
        }

        [$schemaDefinition, $schema] = $this->chainNodeService->parseSchemaDefinition($request->input('output_schema_definition'));

        /** @var ChainNode $node */
        $node = $chain->nodes()->create([
            'tenant_id' => currentTenantId(),
            'name' => $request->string('name'),
            'order_index' => ($chain->nodes()->max('order_index') ?? 0) + 1,
            'provider_credential_id' => $request->integer('provider_credential_id'),
            'model_name' => $request->string('model_name'),
            'model_params' => $request->input('model_params'),
            'messages_config' => $messagesConfig,
            'output_schema_definition' => $schemaDefinition,
            'output_schema' => $schema,
            'stop_on_validation_error' => $request->boolean('stop_on_validation_error'),
        ]);

        $orderIndex = $request->input('order_index');

        $this->chainNodeService->resequenceNodes($chain, $node->id, $orderIndex ? (int) $orderIndex : null);

        return redirect()->route('projects.chains.show', [$project, $chain]);
    }

    public function update(
        UpdateChainNodeRequest $request,
        Project $project,
        Chain $chain,
        ChainNode $chainNode
    ): RedirectResponse {
        $this->assertChainProject($chain, $project);
        $this->assertNodeChain($chainNode, $chain);

        $desiredOrder = $request->has('order_index')
            ? (int) $request->input('order_index')
            : $chainNode->order_index;

        $messagesConfig = $this->chainNodeService->normalizeMessagesConfig($request->input('messages_config'));

        if (empty($messagesConfig)) {
            throw ValidationException::withMessages([
                'messages_config' => ['At least one prompt template is required.'],
            ]);
        }

        [$schemaDefinition, $schema] = $this->chainNodeService->parseSchemaDefinition($request->input('output_schema_definition'));

        $chainNode->update([
            'name' => $request->string('name'),
            'provider_credential_id' => $request->integer('provider_credential_id'),
            'model_name' => $request->string('model_name'),
            'model_params' => $request->input('model_params'),
            'messages_config' => $messagesConfig,
            'output_schema_definition' => $schemaDefinition,
            'output_schema' => $schema,
            'stop_on_validation_error' => $request->boolean('stop_on_validation_error'),
        ]);

        $this->chainNodeService->resequenceNodes($chain, $chainNode->id, $desiredOrder);

        return redirect()->route('projects.chains.show', [$project, $chain]);
    }

    public function destroy(Project $project, Chain $chain, ChainNode $chainNode): RedirectResponse
    {
        $this->assertChainProject($chain, $project);
        $this->assertNodeChain($chainNode, $chain);

        $chainNode->delete();

        $this->chainNodeService->resequenceNodes($chain);

        return redirect()->route('projects.chains.show', [$project, $chain]);
    }

    private function assertChainProject(Chain $chain, Project $project): void
    {
        if ($chain->project_id !== $project->id || $chain->tenant_id !== $project->tenant_id) {
            abort(404);
        }
    }

    private function assertNodeChain(ChainNode $chainNode, Chain $chain): void
    {
        if ($chainNode->chain_id !== $chain->id || $chainNode->tenant_id !== $chain->tenant_id) {
            abort(404);
        }
    }
}
