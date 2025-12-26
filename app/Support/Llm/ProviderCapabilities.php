<?php

namespace App\Support\Llm;

class ProviderCapabilities
{
    /**
     * @return array<string, array{supports_chat: bool, supports_model_listing: bool}>
     */
    public function all(): array
    {
        return [
            'openai' => ['supports_chat' => true, 'supports_model_listing' => true],
            'anthropic' => ['supports_chat' => true, 'supports_model_listing' => true],
            'google' => ['supports_chat' => true, 'supports_model_listing' => true],
            'openrouter' => ['supports_chat' => true, 'supports_model_listing' => true],
        ];
    }

    public function for(string $provider): array
    {
        return $this->all()[$provider] ?? ['supports_chat' => false, 'supports_model_listing' => false];
    }
}
