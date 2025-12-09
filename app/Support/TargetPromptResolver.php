<?php

namespace App\Support;

use App\Models\ChainNode;
use App\Models\RunStep;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class TargetPromptResolver
{
    public function fromMessagesConfig(array $messagesConfig): ?int
    {
        $system = collect($messagesConfig)->firstWhere('role', 'system');
        if ($system && ($id = Arr::get($system, 'prompt_version_id'))) {
            return (int) $id;
        }

        $user = collect($messagesConfig)->firstWhere('role', 'user');
        if ($user && ($id = Arr::get($user, 'prompt_version_id'))) {
            return (int) $id;
        }

        return null;
    }

    public function fromRunStep(?RunStep $runStep): ?int
    {
        if (! $runStep) {
            return null;
        }

        /** @var ChainNode|null $chainNode */
        $chainNode = $runStep->chainNode;

        return $chainNode ? $this->fromMessagesConfig((array) $chainNode->messages_config) : null;
    }

    /**
     * @param \Illuminate\Support\Collection<int, \App\Models\RunStep> $steps
     * @return array<int, int>
     */
    public function collectTargetVersionIds(Collection $steps): array
    {
        return $steps
            ->map(function ($step) {
                $messagesConfig = $step->chainNode ? (array) ($step->chainNode->messages_config ?? []) : [];

                return $this->fromMessagesConfig($messagesConfig);
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
