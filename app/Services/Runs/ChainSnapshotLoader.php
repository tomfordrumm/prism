<?php

namespace App\Services\Runs;

use App\Models\Chain;
use App\Models\ChainNode;
use App\Models\ProviderCredential;
use App\Models\Run;
use Illuminate\Support\Collection;

class ChainSnapshotLoader
{
    /**
     * Hydrate chain nodes from the run snapshot; if missing, snapshot the chain.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\ChainNode>
     */
    public function load(Run $run): Collection
    {
        /** @var Collection<int, array<string, mixed>> $snapshot */
        $snapshot = collect($run->chain_snapshot ?? []);

        if ($snapshot->isEmpty() && $run->chain_id) {
            $chain = Chain::query()
                ->with(['nodes' => fn ($query) => $query->orderBy('order_index')])
                ->find($run->chain_id);

            if ($chain) {
                $snapshot = collect($this->createSnapshot($chain));
                $run->update(['chain_snapshot' => $snapshot->all()]);
            }
        }

        $credentials = ProviderCredential::query()
            ->whereIn('id', $snapshot->pluck('provider_credential_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        /** @var Collection<int, array<string, mixed>> $snapshot */
        $snapshot = $snapshot;

        return $snapshot
            ->map(function (array $data) use ($credentials) {
                $node = new ChainNode();

                foreach ($data as $key => $value) {
                    $node->setAttribute($key, $value);
                }

                if (array_key_exists('provider_credential_id', $data) && $data['provider_credential_id'] !== null) {
                    $node->setRelation('providerCredential', $credentials->get($data['provider_credential_id']));
                }

                return $node;
            })
            ->sortBy('order_index')
            ->values();
    }

    /**
     * Build a snapshot array from a chain definition.
     *
     * @return array<int, array<string, mixed>>
     */
    public function createSnapshot(Chain $chain): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int,\App\Models\ChainNode> $nodes */
        $nodes = $chain->relationLoaded('nodes')
            ? $chain->nodes
            : $chain->nodes()->orderBy('order_index')->get();

        return $nodes
            ->map(function (ChainNode $node): array {
                return [
                    'id' => $node->id,
                    'name' => $node->name,
                    'provider_credential_id' => $node->provider_credential_id ?? null,
                    'model_name' => $node->model_name,
                    'model_params' => $node->model_params,
                    'messages_config' => $node->messages_config,
                    'output_schema' => $node->output_schema,
                    'stop_on_validation_error' => $node->stop_on_validation_error,
                    'order_index' => $node->order_index,
                ];
            })
            ->sortBy('order_index')
            ->values()
            ->all();
    }
}
