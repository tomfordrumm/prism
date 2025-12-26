<?php

namespace App\Services\Llm;

class LlmResponseDto
{
    public function __construct(
        public readonly string $content,
        public readonly array $usage = [],
        public readonly array $raw = [],
        public readonly array $meta = [],
    ) {
    }

    public function tokensIn(): ?int
    {
        return $this->usage['tokens_in'] ?? $this->usage['prompt_tokens'] ?? null;
    }

    public function tokensOut(): ?int
    {
        return $this->usage['tokens_out'] ?? $this->usage['completion_tokens'] ?? null;
    }
}
