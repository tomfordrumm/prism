<?php

namespace App\Services\Runs;

use Illuminate\Support\Str;

class VariableResolver
{
    public function resolve(
        array $templateVariables,
        array $configVariables,
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
                    $resolved[$name] = data_get($input, $path ?? $name);
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

    private function normalizePath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $normalized = preg_replace('/\[(.*?)\]/', '.$1', $path) ?? $path;

        return ltrim($normalized, '.');
    }
}
