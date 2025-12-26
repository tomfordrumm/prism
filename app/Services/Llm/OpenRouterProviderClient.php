<?php

namespace App\Services\Llm;

use App\Models\ProviderCredential;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;
use Illuminate\Http\Client\ConnectionException;

class OpenRouterProviderClient implements LlmProviderClientInterface
{
    private const DEFAULT_TIMEOUT_SECONDS = 90;
    private const RETRY_TIMES = 2;
    private const RETRY_DELAY_MS = 500;

    public function call(
        ProviderCredential $credential,
        string $modelName,
        array $messages,
        array $params = []
    ): LlmResponseDto {
        $retryReasons = [];
        $payload = array_merge([
            'model' => $modelName,
            'messages' => $this->normalizeMessages($messages),
            'stream' => false,
        ], $params);

        if (! array_key_exists('usage', $payload)) {
            $payload['usage'] = ['include' => true];
        }

        try {
            $response = $this->request($credential)
                ->timeout(self::DEFAULT_TIMEOUT_SECONDS)
                ->retry(self::RETRY_TIMES, self::RETRY_DELAY_MS, function ($exception) use (&$retryReasons) {
                    $status = method_exists($exception, 'response') && $exception->response
                        ? $exception->response->status()
                        : null;

                    $shouldRetry = $exception instanceof ConnectionException
                        || ($status !== null && ($status === 408 || $status === 429 || $status >= 500));

                    if ($shouldRetry) {
                        $retryReasons[] = $status !== null ? "http_{$status}" : $exception::class;
                    }

                    return $shouldRetry;
                })
                ->post($this->endpoint($credential, 'chat/completions'), $payload)
                ->throw();
        } catch (Throwable $exception) {
            throw new RuntimeException('OpenRouter call failed: '.$exception->getMessage(), previous: $exception);
        }

        $data = $response->json() ?? [];
        $content = $data['choices'][0]['message']['content'] ?? '';
        if (! is_string($content)) {
            $content = json_encode($content);
        }

        $usage = $data['usage'] ?? [];

        return new LlmResponseDto(
            content: $content ?? '',
            usage: [
                'tokens_in' => $usage['prompt_tokens'] ?? null,
                'tokens_out' => $usage['completion_tokens'] ?? null,
            ],
            raw: $data,
            meta: [
                'retry_count' => count($retryReasons),
                'retry_reasons' => $retryReasons,
            ]
        );
    }

    public function listModels(ProviderCredential $credential): array
    {
        try {
            $response = $this->request($credential)
                ->get($this->endpoint($credential, 'models'))
                ->throw();
        } catch (Throwable $exception) {
            throw new RuntimeException('OpenRouter list models failed: '.$exception->getMessage(), previous: $exception);
        }

        $data = $response->json() ?? [];
        $models = $data['data'] ?? $data['models'] ?? [];

        return collect($models)
            ->map(function ($model) {
                if (! is_array($model)) {
                    return null;
                }

                $id = (string) ($model['id'] ?? $model['name'] ?? '');
                if ($id === '') {
                    return null;
                }

                return [
                    'id' => $id,
                    'name' => $id,
                    'display_name' => (string) ($model['display_name'] ?? $model['name'] ?? $id),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function request(ProviderCredential $credential)
    {
        return Http::withHeaders($this->headers($credential))
            ->acceptJson();
    }

    private function headers(ProviderCredential $credential): array
    {
        $apiKey = $this->decryptApiKey($credential);

        $headers = [
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type' => 'application/json',
        ];

        /** @var array $metadata */
        $metadata = $credential->metadata ?? [];
        $referer = $metadata['referer'] ?? null;
        if (is_string($referer) && $referer !== '') {
            $headers['HTTP-Referer'] = $referer;
        }

        $title = $metadata['title'] ?? null;
        if (is_string($title) && $title !== '') {
            $headers['X-Title'] = $title;
        }

        return $headers;
    }

    private function endpoint(ProviderCredential $credential, string $path): string
    {
        /** @var array $metadata */
        $metadata = $credential->metadata ?? [];
        $baseUrl = $metadata['base_url'] ?? ($metadata['baseUrl'] ?? 'https://openrouter.ai/api/v1/');

        return rtrim($baseUrl, '/').'/'.ltrim($path, '/');
    }

    private function normalizeMessages(array $messages): array
    {
        $normalized = [];

        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }

            $role = $message['role'] ?? 'user';
            $content = $this->normalizeContent($message['content'] ?? '');

            $normalized[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        return $normalized;
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

    private function decryptApiKey(ProviderCredential $credential): string
    {
        try {
            return Crypt::decryptString($credential->encrypted_api_key);
        } catch (Throwable $exception) {
            logger()->warning('OpenRouter decrypt api key failed', [
                'credential_id' => $credential->id,
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            throw $exception;
        }
    }
}
