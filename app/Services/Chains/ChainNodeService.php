<?php

namespace App\Services\Chains;

use App\Models\Chain;
use App\Models\ChainNode;
use App\Support\TsLikeSchemaParser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChainNodeService
{
    public function normalizeMessagesConfig(?array $messagesConfig): array
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

    /**
     * @return array{0: ?string, 1: mixed}
     */
    public function parseSchemaDefinition(?string $definition): array
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

    public function resequenceNodes(Chain $chain, ?int $movingNodeId = null, ?int $targetOrder = null): void
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
}
