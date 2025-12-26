<?php

namespace App\Services\Llm;

use Anthropic\Client as AnthropicClient;
use App\Models\ProviderCredential;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;
use Throwable;

class AnthropicProviderClient implements LlmProviderClientInterface
{
    private const DEFAULT_MAX_TOKENS = 10000;

    public function call(
        ProviderCredential $credential,
        string $modelName,
        array $messages,
        array $params = []
    ): LlmResponseDto {
        $client = $this->makeClient($credential);
        $payload = $this->buildPayload($modelName, $messages, $params);

        try {
            $response = $client->messages->create($payload);
        } catch (Throwable $exception) {
            throw new RuntimeException('Anthropic call failed: '.$exception->getMessage(), previous: $exception);
        }

        $content = $this->extractTextContent($response->content ?? []);
        $usage = [
            'tokens_in' => $response->usage?->input_tokens ?? null,
            'tokens_out' => $response->usage?->output_tokens ?? null,
        ];

        $raw = method_exists($response, '__serialize') ? $response->__serialize() : [];

        return new LlmResponseDto(
            content: $content,
            usage: $usage,
            raw: $raw,
            meta: []
        );
    }

    public function listModels(ProviderCredential $credential): array
    {
        $client = $this->makeClient($credential);

        try {
            $response = $client->models->list([]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Anthropic list models failed: '.$exception->getMessage(), previous: $exception);
        }

        $models = method_exists($response, 'getItems') ? $response->getItems() : [];

        return collect($models)
            ->map(function ($model) {
                $id = $model->id ?? '';

                return [
                    'id' => $id,
                    'name' => $id,
                    'display_name' => $model->display_name ?? $id,
                ];
            })
            ->values()
            ->all();
    }

    private function makeClient(ProviderCredential $credential): AnthropicClient
    {
        try {
            $apiKey = Crypt::decryptString($credential->encrypted_api_key);
        } catch (Throwable $exception) {
            logger()->warning('Anthropic decrypt api key failed', [
                'credential_id' => $credential->id,
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            throw $exception;
        }

        /** @var array $metadata */
        $metadata = $credential->metadata ?? [];
        $baseUrl = $metadata['base_url'] ?? ($metadata['baseUrl'] ?? null);

        return new AnthropicClient(
            apiKey: $apiKey,
            baseUrl: $baseUrl
        );
    }

    private function buildPayload(string $modelName, array $messages, array $params): array
    {
        $systemParts = [];
        $normalizedMessages = [];

        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }

            $role = $message['role'] ?? 'user';
            $content = $this->normalizeContent($message['content'] ?? '');

            if ($role === 'system') {
                if ($content !== '') {
                    $systemParts[] = $content;
                }
                continue;
            }

            if (! in_array($role, ['user', 'assistant'], true)) {
                $role = 'user';
            }

            $normalizedMessages[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        $payload = array_merge([
            'model' => $modelName,
            'messages' => $normalizedMessages,
        ], $params);

        if (! array_key_exists('max_tokens', $payload)
            || ! is_numeric($payload['max_tokens'])
            || (int) $payload['max_tokens'] <= 0) {
            $payload['max_tokens'] = self::DEFAULT_MAX_TOKENS;
        }

        if (! empty($systemParts)) {
            $payload['system'] = implode("\n\n", $systemParts);
        }

        return $payload;
    }

    private function normalizeContent(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        if (is_scalar($content)) {
            return (string) $content;
        }

        if (is_array($content)) {
            return json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return '';
    }

    private function extractTextContent(array $blocks): string
    {
        $parts = [];

        foreach ($blocks as $block) {
            if (is_object($block) && isset($block->type) && $block->type === 'text') {
                $parts[] = $block->text ?? '';
            } elseif (is_array($block) && ($block['type'] ?? null) === 'text') {
                $parts[] = $block['text'] ?? '';
            }
        }

        return implode('', $parts);
    }
}
