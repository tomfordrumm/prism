<?php

namespace App\Support;

use App\Models\ChainNode;
use App\Models\PromptVersion;
use App\Models\RunStep;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class TargetPromptResolver
{
    /**
     * Cache for latest prompt version per template.
     *
     * @var array<int, int|null>
     */
    private array $latestVersionCache = [];

    public function fromMessagesConfig(array $messagesConfig): ?int
    {
        $system = collect($messagesConfig)->firstWhere('role', 'system');
        $systemVersion = $this->resolveVersionIdFromMessage($system);
        if ($systemVersion) {
            return $systemVersion;
        }

        $user = collect($messagesConfig)->firstWhere('role', 'user');
        $userVersion = $this->resolveVersionIdFromMessage($user);
        if ($userVersion) {
            return $userVersion;
        }

        return null;
    }

    public function fromRunStep(?RunStep $runStep): ?int
    {
        if (! $runStep) {
            return null;
        }

        if ($runStep->prompt_version_id) {
            return (int) $runStep->prompt_version_id;
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
        $directVersionIds = $steps
            ->pluck('prompt_version_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $templateIds = $steps
            ->flatMap(function ($step) {
                $messagesConfig = $step->chainNode ? (array) ($step->chainNode->messages_config ?? []) : [];

                return $this->collectTemplateIds($messagesConfig);
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($templateIds) {
            $this->preloadLatestVersions($templateIds);
        }

        return collect($directVersionIds)
            ->merge($steps
            ->map(function ($step) {
                $messagesConfig = $step->chainNode ? (array) ($step->chainNode->messages_config ?? []) : [];

                return $this->fromMessagesConfig($messagesConfig);
            })
            ->filter()
            ->unique()
            ->values())
            ->all();
    }

    private function resolveVersionIdFromMessage($message): ?int
    {
        if (! is_array($message)) {
            return null;
        }

        $versionId = Arr::get($message, 'prompt_version_id');
        if ($versionId) {
            return (int) $versionId;
        }

        $templateId = Arr::get($message, 'prompt_template_id');
        if (! $templateId) {
            return null;
        }

        return $this->latestVersionIdForTemplate((int) $templateId);
    }

    private function latestVersionIdForTemplate(int $templateId): ?int
    {
        if (array_key_exists($templateId, $this->latestVersionCache)) {
            return $this->latestVersionCache[$templateId];
        }

        $this->preloadLatestVersions([$templateId]);

        return $this->latestVersionCache[$templateId] ?? null;
    }

    private function collectTemplateIds(array $messagesConfig): array
    {
        $ids = [];

        foreach (['system', 'user'] as $role) {
            $message = collect($messagesConfig)->firstWhere('role', $role);
            if (is_array($message)) {
                $templateId = Arr::get($message, 'prompt_template_id');
                if ($templateId) {
                    $ids[] = (int) $templateId;
                }
            }
        }

        return $ids;
    }

    private function preloadLatestVersions(array $templateIds): void
    {
        $missingTemplateIds = array_values(array_diff($templateIds, array_keys($this->latestVersionCache)));

        if (empty($missingTemplateIds)) {
            return;
        }

        $latestVersions = PromptVersion::query()
            ->select('id', 'prompt_template_id', 'version')
            ->where('tenant_id', currentTenantId())
            ->whereIn('prompt_template_id', $missingTemplateIds)
            ->orderBy('prompt_template_id')
            ->orderByDesc('version')
            ->get()
            ->unique('prompt_template_id');

        foreach ($latestVersions as $version) {
            $this->latestVersionCache[$version->prompt_template_id] = (int) $version->id;
        }

        foreach ($missingTemplateIds as $templateId) {
            if (! array_key_exists($templateId, $this->latestVersionCache)) {
                $this->latestVersionCache[$templateId] = null;
            }
        }
    }
}
