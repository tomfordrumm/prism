<?php

namespace App\Services\Llm;

use App\Models\ProviderCredential;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class ModelCatalog
{
    public function __construct(
        private OpenAiProviderClient $openAiProviderClient,
        private StubProviderClient $stubProviderClient,
    ) {
    }

    /**
     * @return array<int, array{id: string, name: string, display_name: string}>
     */
    public function getModelsFor(ProviderCredential $credential): array
    {
        $cacheKey = "provider_models:{$credential->provider}:{$credential->id}";

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($credential) {
            $client = $this->resolveClient($credential);

            try {
                $models = $client->listModels($credential);
            } catch (\Throwable $exception) {
                logger()->warning('ModelCatalog: failed to list models via provider client', [
                    'provider' => $credential->provider,
                    'credential_id' => $credential->id,
                    'error' => $exception->getMessage(),
                ]);
                $models = [];
            }

            if (empty($models)) {
                $models = config('llm.models')[$credential->provider] ?? [];
            }

            return $this->normalizeModels($models);
        });
    }

    private function resolveClient(ProviderCredential $credential): LlmProviderClientInterface
    {
        return match ($credential->provider) {
            'openai' => $this->openAiProviderClient,
            'anthropic', 'google' => $this->stubProviderClient,
            default => throw new InvalidArgumentException('Unsupported provider: '.$credential->provider),
        };
    }

    private function normalizeModels(array $models): array
    {
        return array_map(function (array $model): array {
            $id = (string) ($model['id'] ?? $model['name'] ?? '');
            $name = (string) ($model['name'] ?? $id);
            $displayName = (string) ($model['display_name'] ?? $name);

            return [
                'id' => $id,
                'name' => $name,
                'display_name' => $displayName,
            ];
        }, $models);
    }
}
