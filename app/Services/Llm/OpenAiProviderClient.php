<?php

namespace App\Services\Llm;

use App\Models\ProviderCredential;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use OpenAI\Exceptions\ErrorException;
use RuntimeException;
use Throwable;
use OpenAI\Factory as OpenAIFactory;
use OpenAI\Responses\Models\ListResponseModels;

class OpenAiProviderClient implements LlmProviderClientInterface
{
    public function call(
        ProviderCredential $credential,
        string $modelName,
        array $messages,
        array $params = []
    ): LlmResponseDto {
        $client = $this->makeClient($credential);

        try {
            $response = $client->chat()->create(array_merge([
                'model' => $modelName,
                'messages' => $messages,
            ], $params));
        } catch (Throwable $exception) {
            throw new RuntimeException('OpenAI call failed: '.$exception->getMessage(), previous: $exception);
        }

        $raw = $response->toArray();
        $content = data_get($raw, 'choices.0.message.content', '');
        if ($content === '' && ! data_get($raw, 'choices', null)) {
            logger()->warning('OpenAI response missing choices', [
                'credential_id' => $credential->id,
                'model' => $modelName,
                'raw_keys' => array_keys($raw),
                'raw_preview' => Str::limit(json_encode($raw), 1500),
            ]);
        }

        $usage = [
            'tokens_in' => data_get($raw, 'usage.prompt_tokens'),
            'tokens_out' => data_get($raw, 'usage.completion_tokens'),
        ];

        return new LlmResponseDto(
            content: is_string($content) ? $content : json_encode($content),
            usage: $usage,
            raw: $raw,
            meta: []
        );
    }

    public function listModels(ProviderCredential $credential): array
    {
        $client = $this->makeClient($credential);

        try {
            $response = $client->models()->list();
        } catch (Throwable $exception) {
            if ($exception instanceof ErrorException) {
                logger()->warning('OpenAI list models failed', [
                    'credential_id' => $credential->id,
                    'status' => $exception->getStatusCode(),
                    'error_type' => $exception->getErrorType(),
                    'error_code' => $exception->getErrorCode(),
                    'error_message' => $exception->getErrorMessage(),
                ]);
            }
            throw new RuntimeException('OpenAI list models failed: '.$exception->getMessage(), previous: $exception);
        }

        return $this->mapModelList($response);
    }

    private function makeClient(ProviderCredential $credential)
    {
        try {
            $apiKey = Crypt::decryptString($credential->encrypted_api_key);
        } catch (Throwable $exception) {
            logger()->warning('OpenAI decrypt api key failed', [
                'credential_id' => $credential->id,
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            throw $exception;
        }
        $factory = (new OpenAIFactory())->withApiKey($apiKey);

        /** @var array $metadata */
        $metadata = $credential->metadata ?? [];
        $baseUrl = $metadata['base_url'] ?? ($metadata['baseUrl'] ?? null);
        if ($baseUrl) {
            $factory = $factory->withBaseUri($baseUrl);
        }

        return $factory->make();
    }

    private function mapModelList($response): array
    {
        $models = [];

        if (is_object($response) && isset($response->data) && is_iterable($response->data)) {
            $models = $response->data;
        }

        return collect($models)
            ->map(function ($model) {
                $id = $model->id ?? '';

                return [
                    'id' => $id,
                    'name' => $id,
                    'display_name' => $model->id ?? $id,
                ];
            })
            ->values()
            ->all();
    }
}
