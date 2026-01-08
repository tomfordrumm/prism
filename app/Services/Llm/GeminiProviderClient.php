<?php

namespace App\Services\Llm;

use App\Models\ProviderCredential;
use Gemini\Data\Content;
use Gemini\Data\GenerationConfig;
use Gemini\Enums\Role;
use Gemini\Factory as GeminiFactory;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;
use Throwable;

class GeminiProviderClient implements LlmProviderClientInterface
{
    public function call(
        ProviderCredential $credential,
        string $modelName,
        array $messages,
        array $params = []
    ): LlmResponseDto {
        $client = $this->makeClient($credential);
        $systemInstruction = $this->buildSystemInstruction($messages);
        $contents = $this->buildContents($messages);
        $generationConfig = $this->buildGenerationConfig($params);

        if (empty($contents)) {
            $contents = [Content::parse('')];
        }

        $model = $client->generativeModel(model: $modelName);

        if ($systemInstruction) {
            $model = $model->withSystemInstruction($systemInstruction);
        }

        if ($generationConfig) {
            $model = $model->withGenerationConfig($generationConfig);
        }

        try {
            $response = $model->generateContent(...$contents);
        } catch (Throwable $exception) {
            throw new RuntimeException('Gemini call failed: '.$exception->getMessage(), previous: $exception);
        }

        $content = $this->extractTextContent($response->candidates ?? []);
        $usageMetadata = $response->usageMetadata ?? null;
        $tokensIn = $usageMetadata?->promptTokenCount ?? null;
        $tokensOut = $usageMetadata?->candidatesTokenCount ?? null;
        if ($tokensOut === null && $usageMetadata?->totalTokenCount !== null && $tokensIn !== null) {
            $tokensOut = max(0, $usageMetadata->totalTokenCount - $tokensIn);
        }

        return new LlmResponseDto(
            content: $content,
            usage: [
                'tokens_in' => $tokensIn,
                'tokens_out' => $tokensOut,
            ],
            raw: $response->toArray(),
            meta: []
        );
    }

    public function listModels(ProviderCredential $credential): array
    {
        $client = $this->makeClient($credential);

        try {
            $response = $client->models()->list();
        } catch (Throwable $exception) {
            throw new RuntimeException('Gemini list models failed: '.$exception->getMessage(), previous: $exception);
        }

        return collect($response->models ?? [])
            ->map(function ($model) {
                $name = $model->name ?? '';
                $id = $this->normalizeModelId($name);

                return [
                    'id' => $id,
                    'name' => $id,
                    'display_name' => $model->displayName ?? $id,
                ];
            })
            ->values()
            ->all();
    }

    private function makeClient(ProviderCredential $credential)
    {
        try {
            $apiKey = Crypt::decryptString($credential->encrypted_api_key);
        } catch (Throwable $exception) {
            logger()->warning('Gemini decrypt api key failed', [
                'credential_id' => $credential->id,
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            throw $exception;
        }

        $factory = (new GeminiFactory())->withApiKey($apiKey);

        /** @var array $metadata */
        $metadata = $credential->metadata ?? [];
        $baseUrl = $metadata['base_url'] ?? ($metadata['baseUrl'] ?? null);
        if ($baseUrl) {
            $factory = $factory->withBaseUrl($baseUrl);
        }

        return $factory->make();
    }

    private function buildSystemInstruction(array $messages): ?Content
    {
        $systemParts = [];

        foreach ($messages as $message) {
            if (! is_array($message) || ($message['role'] ?? null) !== 'system') {
                continue;
            }

            $content = $this->normalizeContent($message['content'] ?? '');
            if ($content !== '') {
                $systemParts[] = $content;
            }
        }

        if (empty($systemParts)) {
            return null;
        }

        return Content::parse(implode("\n\n", $systemParts), Role::USER);
    }

    /**
     * @return array<int, Content>
     */
    private function buildContents(array $messages): array
    {
        $contents = [];

        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }

            $role = $message['role'] ?? 'user';
            if ($role === 'system') {
                continue;
            }

            $content = $this->normalizeContent($message['content'] ?? '');
            $contents[] = Content::parse(
                $content,
                $role === 'assistant' ? Role::MODEL : Role::USER
            );
        }

        return $contents;
    }

    private function buildGenerationConfig(array $params): ?GenerationConfig
    {
        $temperature = $this->extractFloat($params, 'temperature');
        $topP = $this->extractFloat($params, 'top_p');
        $topK = $this->extractInt($params, 'top_k');
        $maxTokens = $this->extractInt($params, 'max_tokens');
        $stopSequences = $this->extractStringArray($params, 'stop_sequences');

        $hasConfig = $temperature !== null
            || $topP !== null
            || $topK !== null
            || $maxTokens !== null
            || $stopSequences !== null;

        if (! $hasConfig) {
            return null;
        }

        return new GenerationConfig(
            stopSequences: $stopSequences ?? [],
            maxOutputTokens: $maxTokens,
            temperature: $temperature,
            topP: $topP,
            topK: $topK
        );
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

    private function extractTextContent(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $content = is_array($candidate)
                ? ($candidate['content'] ?? null)
                : ($candidate->content ?? null);
            $parts = [];

            if (is_array($content)) {
                $parts = $content['parts'] ?? [];
            } elseif (is_object($content)) {
                $parts = $content->parts ?? [];
            }

            $textParts = [];

            foreach ($parts as $part) {
                $text = is_array($part)
                    ? ($part['text'] ?? null)
                    : ($part->text ?? null);
                if (is_string($text) && $text !== '') {
                    $textParts[] = $text;
                }
            }

            if (! empty($textParts)) {
                return implode('', $textParts);
            }
        }

        return '';
    }

    private function normalizeModelId(string $name): string
    {
        return str_starts_with($name, 'models/')
            ? substr($name, strlen('models/'))
            : $name;
    }

    private function extractFloat(array $params, string $key): ?float
    {
        if (! array_key_exists($key, $params)) {
            return null;
        }

        $value = $params[$key];
        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    private function extractInt(array $params, string $key): ?int
    {
        if (! array_key_exists($key, $params)) {
            return null;
        }

        $value = $params[$key];
        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function extractStringArray(array $params, string $key): ?array
    {
        if (! array_key_exists($key, $params)) {
            return null;
        }

        $value = $params[$key];
        if (is_array($value)) {
            return array_values(array_filter($value, 'is_string'));
        }

        if (is_string($value) && $value !== '') {
            return [$value];
        }

        return null;
    }
}
