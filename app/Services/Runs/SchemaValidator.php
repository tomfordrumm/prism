<?php

namespace App\Services\Runs;

use App\Services\Llm\LlmResponseDto;

class SchemaValidator
{
    /**
     * @return array{0: mixed, 1: array<int, string>}
     */
    public function parseAndValidate(?LlmResponseDto $responseDto, ?array $schema): array
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

        if (! $type) {
            return ["{$path} schema type is required."];
        }

        if (! in_array($type, ['string', 'number', 'boolean', 'enum', 'array', 'object'], true)) {
            return ["{$path} has unsupported schema type: {$type}."];
        }

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
                if (! is_array($values)) {
                    $errors[] = "{$path} enum values must be an array.";
                    break;
                }
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
