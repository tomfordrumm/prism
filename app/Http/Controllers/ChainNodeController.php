<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChainNodes\StoreChainNodeRequest;
use App\Http\Requests\ChainNodes\UpdateChainNodeRequest;
use App\Models\Chain;
use App\Models\ChainNode;
use App\Models\Project;
use App\Support\TsLikeSchemaParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChainNodeController extends Controller
{
    public function store(StoreChainNodeRequest $request, Project $project, Chain $chain): RedirectResponse
    {
        $this->assertChainProject($chain, $project);

        $messagesConfig = $this->normalizeMessagesConfig($request->input('messages_config'));

        if (empty($messagesConfig)) {
            throw ValidationException::withMessages([
                'messages_config' => ['At least one prompt template is required.'],
            ]);
        }

        [$schemaDefinition, $schema] = $this->parseSchemaDefinition($request->input('output_schema_definition'));

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

        $this->resequenceNodes($chain, $node->id, $orderIndex ? (int) $orderIndex : null);

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

        $messagesConfig = $this->normalizeMessagesConfig($request->input('messages_config'));

        if (empty($messagesConfig)) {
            throw ValidationException::withMessages([
                'messages_config' => ['At least one prompt template is required.'],
            ]);
        }

        [$schemaDefinition, $schema] = $this->parseSchemaDefinition($request->input('output_schema_definition'));

        DB::transaction(function () use ($request, $chain, $chainNode, $desiredOrder, $messagesConfig, $schemaDefinition, $schema): void {
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

            $this->resequenceNodes($chain, $chainNode->id, $desiredOrder);
        });

        return redirect()->route('projects.chains.show', [$project, $chain]);
    }

    public function destroy(Project $project, Chain $chain, ChainNode $chainNode): RedirectResponse
    {
        $this->assertChainProject($chain, $project);
        $this->assertNodeChain($chainNode, $chain);

        $chainNode->delete();

        $this->resequenceNodes($chain);

        return redirect()->route('projects.chains.show', [$project, $chain]);
    }

    private function normalizeMessagesConfig(?array $messagesConfig): array
    {
        return collect($messagesConfig ?? [])
            ->filter(fn ($item) => isset($item['role']))
            ->map(fn ($item) => [
                'role' => (string) $item['role'],
                'mode' => $item['mode'] ?? 'template',
                'prompt_template_id' => $item['prompt_template_id'] ?? null,
                'prompt_version_id' => $item['prompt_version_id'] ?? null,
                'inline_content' => $item['inline_content'] ?? null,
                'variables' => isset($item['variables']) && is_array($item['variables']) ? $item['variables'] : [],
            ])
            ->values()
            ->all();
    }

    private function parseSchemaDefinition(?string $definition): array
    {
        if (! $definition) {
            return [null, null];
        }

        $parser = new TsLikeSchemaParser();

        try {
            $schema = $parser->parse($definition);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'output_schema_definition' => $e->getMessage(),
            ]);
        }

        return [$definition, $schema];
    }

    private function resequenceNodes(Chain $chain, ?int $movingNodeId = null, ?int $targetOrder = null): void
    {
        DB::transaction(function () use ($chain, $movingNodeId, $targetOrder): void {
            /** @var Collection<int, ChainNode> $nodes */
            $nodes = $chain->nodes()->orderBy('order_index')->get();

            if ($movingNodeId && $targetOrder) {
                $movingNode = $nodes->firstWhere('id', $movingNodeId);
                $nodes = $nodes->reject(fn (ChainNode $node) => $node->id === $movingNodeId)->values();

                if ($movingNode) {
                    $targetOrder = max(1, $targetOrder);
                    $targetOrder = min($targetOrder, $nodes->count() + 1);
                    $nodes->splice($targetOrder - 1, 0, [$movingNode]);
                }
            }

            $nodes->values()->each(function (ChainNode $node, int $index): void {
                $node->order_index = $index + 1;
                $node->save();
            });
        });
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
