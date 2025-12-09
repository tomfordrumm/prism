<?php

namespace App\Actions\Runs;

use App\Models\Chain;
use App\Models\ChainNode;
use App\Models\PromptVersion;
use App\Models\ProviderCredential;
use App\Models\Run;
use App\Models\RunStep;
use App\Services\Llm\LlmResponseDto;
use App\Services\Llm\LlmService;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RunChainAction
{
    public function __construct(private LlmService $llmService)
    {
    }

    public function execute(Run $run): Run
    {
        try {
            $runStart = now();
            $run->update([
                'status' => 'running',
                'started_at' => $run->started_at ?? now(),
            ]);

            $nodes = $this->hydrateNodesFromSnapshot($run);

            $promptVersions = $this->loadPromptVersions($nodes);
            $stepOutputs = [];

            $totalTokensIn = 0;
            $totalTokensOut = 0;
            $failed = false;

            foreach ($nodes as $node) {
                $stepStart = microtime(true);
                $messages = $this->buildMessages($node, $promptVersions, $run->input ?? [], $stepOutputs);
                $params = $node->model_params ?? [];

                $responseDto = null;
                $validationErrors = [];
                $parsedOutput = null;
                $status = 'success';

                try {
                    if (! $node->providerCredential) {
                        throw new \RuntimeException('Provider credential is missing for node '.$node->id);
                    }

                    $responseDto = $this->llmService->call(
                        $node->providerCredential,
                        $node->model_name,
                        $messages,
                        $params
                    );

                    [$parsedOutput, $validationErrors] = $this->validateOutput(
                        $responseDto,
                        $node->output_schema
                    );

                    if ($validationErrors && $node->stop_on_validation_error) {
                        $status = 'failed';
                        $failed = true;
                    }
                } catch (\Throwable $e) {
                    Log::error('RunChainAction step failed', [
                        'run_id' => $run->id,
                        'chain_node_id' => $node->id,
                        'error' => $e->getMessage(),
                    ]);

                    $validationErrors[] = 'LLM call failed: '.$e->getMessage();
                    $status = 'failed';
                    $failed = true;
                }

                $durationMs = (int) ((microtime(true) - $stepStart) * 1000);
                $usage = $responseDto ? $responseDto->usage : [];
                $totalTokensIn += $responseDto?->tokensIn() ?? 0;
                $totalTokensOut += $responseDto?->tokensOut() ?? 0;

                RunStep::create([
                    'tenant_id' => currentTenantId(),
                    'run_id' => $run->id,
                    'chain_node_id' => $node->id,
                    'order_index' => $node->order_index,
                    'request_payload' => [
                        'model' => $node->model_name,
                        'params' => $params,
                        'messages' => $messages,
                    ],
                    'response_raw' => $responseDto ? $responseDto->raw : [],
                    'parsed_output' => $parsedOutput,
                    'tokens_in' => $usage['tokens_in'] ?? null,
                    'tokens_out' => $usage['tokens_out'] ?? null,
                    'duration_ms' => $durationMs,
                    'validation_errors' => $validationErrors ?: null,
                    'status' => $status,
                ]);

                $stepKey = $this->stepKey($node);
                $stepOutputs[$stepKey] = [
                    'parsed_output' => $parsedOutput,
                    'raw_output' => $responseDto ? $responseDto->content : null,
                    'response_raw' => $responseDto ? $responseDto->raw : null,
                ];

                if ($failed) {
                    break;
                }
            }

            $run->update([
                'status' => $failed ? 'failed' : 'success',
                'total_tokens_in' => $totalTokensIn ?: null,
                'total_tokens_out' => $totalTokensOut ?: null,
                'duration_ms' => now()->diffInMilliseconds($runStart),
                'finished_at' => now(),
            ]);

            return $run;
        } catch (\Throwable $e) {
            Log::error('RunChainAction: fatal failure', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);

            $run->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            return $run;
        }
    }

    private function hydrateNodesFromSnapshot(Run $run): Collection
    {
        /** @var Collection<int, array<string, mixed>> $snapshot */
        $snapshot = collect($run->chain_snapshot ?? []);

        if ($snapshot->isEmpty() && $run->chain_id) {
            $chain = Chain::query()
                ->with(['nodes' => fn ($query) => $query->orderBy('order_index')])
                ->find($run->chain_id);

            if ($chain) {
                $nodeArray = $chain->nodes
                    ->map(function ($node): array {
                        /** @var ChainNode $node */
                        return [
                            'id' => $node->id,
                            'name' => $node->name,
                            'provider_credential_id' => $node->provider_credential_id ?? null,
                            'model_name' => $node->model_name,
                            'model_params' => $node->model_params,
                            'messages_config' => $node->messages_config,
                            'output_schema' => $node->output_schema,
                            'stop_on_validation_error' => $node->stop_on_validation_error,
                            'order_index' => $node->order_index,
                        ];
                    })
                    ->values()
                    ->all();

                /** @var Collection<int, array<string, mixed>> $snapshot */
                $snapshot = collect($nodeArray);

                $run->update(['chain_snapshot' => $snapshot->all()]);
            }
        }

        $credentials = ProviderCredential::query()
            ->whereIn('id', $snapshot->pluck('provider_credential_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        /** @var Collection<int, array<string, mixed>> $snapshot */
        $snapshot = $snapshot;

        return $snapshot
            ->map(function (array $data) use ($credentials) {
                $node = new ChainNode();

                foreach ($data as $key => $value) {
                    $node->setAttribute($key, $value);
                }

                if (! array_key_exists('provider_credential_id', $data) || $data['provider_credential_id'] === null) {
                    return $node;
                }

                $credentialId = $data['provider_credential_id'];
                $node->setRelation('providerCredential', $credentials->get($credentialId));

                return $node;
            })
            ->sortBy('order_index')
            ->values();
    }

    private function loadPromptVersions(Collection $nodes): array
    {
        $configs = $nodes->flatMap(fn (ChainNode $node) => collect($node->messages_config ?? []));

        $templateIds = $configs
            ->pluck('prompt_template_id')
            ->filter()
            ->unique()
            ->values();

        $versionIds = $configs
            ->pluck('prompt_version_id')
            ->filter()
            ->unique()
            ->values();

        if ($templateIds->isEmpty() && $versionIds->isEmpty()) {
            return ['by_id' => collect(), 'by_template' => collect()];
        }

        $versions = PromptVersion::query()
            ->with('promptTemplate:id,name,variables')
            ->where(function ($query) use ($versionIds, $templateIds) {
                if ($versionIds->isNotEmpty()) {
                    $query->whereIn('id', $versionIds);
                }

                if ($templateIds->isNotEmpty()) {
                    $query->orWhereIn('prompt_template_id', $templateIds);
                }
            })
            ->get();

        return [
            'by_id' => $versions->keyBy('id'),
            'by_template' => $versions
                ->groupBy('prompt_template_id')
                ->map(fn ($group) => $group->sortByDesc('version')->first()),
        ];
    }

    private function buildMessages(
        ChainNode $node,
        array $promptVersions,
        array $input,
        array $stepContext
    ): array {
        $configs = collect($node->messages_config ?? [])
            ->filter('is_array')
            ->values()
            ->all();

        /** @var array<int, array<string, mixed>> $configs */
        return collect($configs)
            ->map(function (array $config) use ($promptVersions, $input, $stepContext) {
                $versionId = $config['prompt_version_id'] ?? null;
                $templateId = $config['prompt_template_id'] ?? null;
                $mode = $config['mode'] ?? 'template';

                $promptVersion = null;

                if ($versionId) {
                    $promptVersion = $promptVersions['by_id']->get($versionId);
                } elseif ($templateId) {
                    $promptVersion = $promptVersions['by_template']->get($templateId);
                }

                $content = $mode === 'inline'
                    ? ($config['inline_content'] ?? '')
                    : ($promptVersion ? $promptVersion->content : '');

                $configVariables = $config['variables'] ?? [];
                if (! is_array($configVariables)) {
                    $configVariables = [];
                }

                $templateVariables = $mode === 'inline'
                    ? $this->parseInlineVariables($content)
                    : ($promptVersion ? ($promptVersion->promptTemplate->variables ?? []) : []);

                $resolvedVariables = $this->resolveVariables(
                    $configVariables,
                    $templateVariables,
                    $input,
                    $stepContext
                );

                return [
                    'role' => $config['role'] ?? 'user',
                    'content' => $this->applyVariables($content, $resolvedVariables),
                ];
            })
            ->values()
            ->all();
    }

    private function applyVariables(string $content, array $variables): string
    {
        return preg_replace_callback('/{{\s*(.*?)\s*}}/', function ($matches) use ($variables) {
            $key = $matches[1];

            return (string) Arr::get($variables, $key, '');
        }, $content) ?? $content;
    }

    private function resolveVariables(
        array $configVariables,
        array $templateVariables,
        array $input,
        array $stepContext
    ): array {
        $names = collect($templateVariables)
            ->map(function ($item) {
                if (is_array($item) && isset($item['name'])) {
                    return $item['name'];
                }

                if (is_string($item)) {
                    return $item;
                }

                return null;
            })
            ->filter()
            ->unique()
            ->values();

        $resolved = [];

        foreach ($names as $name) {
            $mapping = $configVariables[$name] ?? null;

            if (! $mapping) {
                $resolved[$name] = data_get($input, $name);
                continue;
            }

            $path = $this->normalizePath($mapping['path'] ?? null);
            $source = $mapping['source'] ?? null;

            switch ($source) {
                case 'input':
                    $resolved[$name] = data_get($input, $path);
                    break;
                case 'previous_step':
                    $stepKey = $mapping['step_key'] ?? null;
                    if ($stepKey && array_key_exists($stepKey, $stepContext)) {
                        $fromStep = $stepContext[$stepKey];

                        $value = $path ? data_get($fromStep, $path) : null;

                        if ($value === null && $path && ! Str::startsWith($path, ['parsed_output', 'raw_output', 'response_raw'])) {
                            $value = data_get($fromStep['parsed_output'] ?? [], $path);
                        }

                        if ($value === null && ! $path) {
                            $value = $fromStep['parsed_output'] ?? ($fromStep['raw_output'] ?? null);
                        }

                        $resolved[$name] = $value;
                    } else {
                        $resolved[$name] = null;
                    }
                    break;
                case 'constant':
                    $resolved[$name] = $mapping['value'] ?? null;
                    break;
                default:
                    $resolved[$name] = data_get($input, $path ?? $name);
                    break;
            }
        }

        return $resolved;
    }

    private function stepKey(ChainNode $node): string
    {
        $key = Str::slug($node->name, '_');

        return $key ?: 'step_'.$node->id;
    }

    private function parseInlineVariables(string $content): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z_][a-zA-Z0-9_.]*)\s*\}\}/', $content, $matches);

        $names = $matches[1];

        return collect($names)->map(fn ($name) => ['name' => $name])->all();
    }

    private function normalizePath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $normalized = preg_replace('/\[(.*?)\]/', '.$1', $path) ?? $path;

        return ltrim($normalized, '.');
    }

    private function validateOutput(?LlmResponseDto $responseDto, ?array $schema): array
    {
        if (! $responseDto) {
            return [null, ['No response received']];
        }

        $parsed = json_decode($responseDto->content, true);

        if (! $schema) {
            return [json_last_error() === JSON_ERROR_NONE ? $parsed : null, []];
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [null, ['Response is not valid JSON']];
        }

        $errors = $this->validateAgainstSchema($parsed, $schema, 'response');

        return [$parsed, $errors];
    }

    private function validateAgainstSchema(mixed $data, array $schema, string $path): array
    {
        $type = $schema['type'] ?? null;
        $errors = [];

        switch ($type) {
            case 'string':
                if (! is_string($data)) {
                    $errors[] = "{$path} must be a string.";
                }
                break;
            case 'number':
                if (! is_int($data) && ! is_float($data)) {
                    $errors[] = "{$path} must be a number.";
                }
                break;
            case 'boolean':
                if (! is_bool($data)) {
                    $errors[] = "{$path} must be a boolean.";
                }
                break;
            case 'enum':
                $values = $schema['values'] ?? [];
                if (! in_array($data, $values, true)) {
                    $errors[] = "{$path} must be one of: ".implode(', ', $values).'.';
                }
                break;
            case 'array':
                if (! is_array($data)) {
                    $errors[] = "{$path} must be an array.";
                    break;
                }

                if (isset($schema['items'])) {
                    foreach ($data as $index => $item) {
                        $errors = array_merge(
                            $errors,
                            $this->validateAgainstSchema($item, $schema['items'], $this->joinPath($path, '['.$index.']'))
                        );
                    }
                }
                break;
            case 'object':
                if (! is_array($data)) {
                    $errors[] = "{$path} must be an object.";
                    break;
                }

                $fields = $schema['fields'] ?? [];

                foreach ($fields as $fieldName => $fieldSchema) {
                    $fieldPath = $this->joinPath($path, $fieldName);
                    $isRequired = (bool) ($fieldSchema['required'] ?? false);

                    if (array_key_exists($fieldName, $data)) {
                        $errors = array_merge(
                            $errors,
                            $this->validateAgainstSchema($data[$fieldName], $fieldSchema, $fieldPath)
                        );
                    } elseif ($isRequired) {
                        $errors[] = "{$fieldPath} is required.";
                    }
                }
                break;
        }

        return $errors;
    }

    private function joinPath(string $base, string $segment): string
    {
        if ($base === '') {
            return $segment;
        }

        if (str_starts_with($segment, '[')) {
            return $base.$segment;
        }

        return $base.'.'.$segment;
    }
}
