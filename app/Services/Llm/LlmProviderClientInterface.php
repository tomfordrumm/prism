<?php

namespace App\Services\Llm;

use App\Models\ProviderCredential;

interface LlmProviderClientInterface
{
    public function call(
        ProviderCredential $credential,
        string $modelName,
        array $messages,
        array $params = []
    ): LlmResponseDto;

    /**
     * @return array<int, array{id: string, name: string, display_name: string}>
     */
    public function listModels(ProviderCredential $credential): array;
}
