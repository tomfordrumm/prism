<?php

namespace App\Services\Runs;

use App\Models\ChainNode;
use App\Models\PromptVersion;
use Illuminate\Support\Collection;

class PromptVersionResolver
{
    /**
     * @param \Illuminate\Support\Collection<int, \App\Models\ChainNode> $nodes
     * @return array{by_id: \Illuminate\Support\Collection<int, \App\Models\PromptVersion>, by_template: \Illuminate\Support\Collection<int, \App\Models\PromptVersion>}
     */
    public function loadForNodes(Collection $nodes): array
    {
        $configs = $nodes->flatMap(fn (ChainNode $node) => collect($node->messages_config ?? []));

        $templateIds = $configs
            ->pluck('prompt_template_id')
            ->filter()
            ->unique()
            ->values();

        $versionIds = $configs
            ->pluck('prompt_version_id')
            ->filter()
            ->unique()
            ->values();

        if ($templateIds->isEmpty() && $versionIds->isEmpty()) {
            return ['by_id' => collect(), 'by_template' => collect()];
        }

        $versions = PromptVersion::query()
            ->with('promptTemplate:id,name,variables')
            ->where(function ($query) use ($versionIds, $templateIds) {
                if ($versionIds->isNotEmpty()) {
                    $query->whereIn('id', $versionIds);
                }

                if ($templateIds->isNotEmpty()) {
                    $query->orWhereIn('prompt_template_id', $templateIds);
                }
            })
            ->get();

        return [
            'by_id' => $versions->keyBy('id'),
            'by_template' => $versions
                ->groupBy('prompt_template_id')
                ->map(fn ($group) => $group->sortByDesc('version')->first()),
        ];
    }
}
