<?php

namespace App\Services\Llm;

use App\Models\ProviderCredential;

class StubProviderClient implements LlmProviderClientInterface
{
    public function call(
        ProviderCredential $credential,
        string $modelName,
        array $messages,
        array $params = []
    ): LlmResponseDto {
        $joined = collect($messages)
            ->map(fn ($message) => ($message['role'] ?? 'user').': '.$message['content'])
            ->implode("\n");

        $content = "Stub response from {$credential->provider} ({$modelName}).\nInput:\n".$joined;

        return new LlmResponseDto($content, usage: ['tokens_in' => null, 'tokens_out' => null], raw: [], meta: []);
    }

    public function listModels(ProviderCredential $credential): array
    {
        return [];
    }
}
