<?php

namespace App\Services\Llm;

use App\Models\ProviderCredential;
use InvalidArgumentException;

class LlmService
{
    public function __construct(
        private OpenAiProviderClient $openAiProviderClient,
        private StubProviderClient $stubProviderClient
    ) {
    }

    public function call(
        ProviderCredential $credential,
        string $modelName,
        array $messages,
        array $params = []
    ): LlmResponseDto {
        $client = $this->resolveClient($credential);

        return $client->call($credential, $modelName, $messages, $params);
    }

    private function resolveClient(ProviderCredential $credential): LlmProviderClientInterface
    {
        return match ($credential->provider) {
            'openai' => $this->openAiProviderClient,
            'anthropic', 'google' => $this->stubProviderClient,
            default => throw new InvalidArgumentException('Unsupported provider: '.$credential->provider),
        };
    }
}
