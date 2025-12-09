<?php

namespace App\Services\Runs;

use App\Models\ChainNode;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class MessageBuilder
{
    public function __construct(private VariableResolver $variableResolver)
    {
    }

    public function build(
        ChainNode $node,
        array $promptVersions,
        array $input,
        array $stepContext
    ): array {
        $configs = collect($node->messages_config ?? [])
            ->filter(fn ($item) => is_array($item))
            ->values()
            ->all();

        /** @var array<int, array<string, mixed>> $configs */
        return collect($configs)
            ->map(function (array $config) use ($promptVersions, $input, $stepContext, $node) {
                $mode = $this->normalizeMode($config['mode'] ?? null);
                $versionId = $config['prompt_version_id'] ?? null;
                $templateId = $config['prompt_template_id'] ?? null;

                $promptVersion = null;

                if ($versionId) {
                    $promptVersion = $promptVersions['by_id']->get($versionId);
                } elseif ($templateId) {
                    $promptVersion = $promptVersions['by_template']->get($templateId);
                }

                $content = $mode === 'inline'
                    ? ($config['inline_content'] ?? '')
                    : ($promptVersion ? $promptVersion->content : '');

                if ($mode === 'template' && ! $promptVersion) {
                    Log::warning('MessageBuilder: prompt version not found', [
                        'chain_node_id' => $node->id,
                        'prompt_version_id' => $versionId,
                        'prompt_template_id' => $templateId,
                    ]);
                }

                $configVariables = $config['variables'] ?? [];
                if (! is_array($configVariables)) {
                    $configVariables = [];
                }

                $templateVariables = $mode === 'inline'
                    ? $this->parseInlineVariables($content)
                    : ($promptVersion ? ($promptVersion->promptTemplate->variables ?? []) : []);

                $resolvedVariables = $this->variableResolver->resolve(
                    $templateVariables,
                    $configVariables,
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

            $value = Arr::get($variables, $key, '');

            return is_scalar($value) || $value === null ? (string) $value : '';
        }, $content) ?? $content;
    }

    private function parseInlineVariables(string $content): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z_][a-zA-Z0-9_.]*)\s*\}\}/', $content, $matches);

        $names = $matches[1];

        return collect($names)->map(fn ($name) => ['name' => $name])->all();
    }

    private function normalizeMode(?string $mode): string
    {
        return in_array($mode, ['template', 'inline'], true) ? $mode : 'template';
    }
}
