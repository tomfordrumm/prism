<?php

namespace App\Support;

use InvalidArgumentException;

class TsLikeSchemaParser
{
    public function parse(?string $definition): ?array
    {
        if (! $definition || ! trim($definition)) {
            return null;
        }

        $trimmed = trim($definition);

        if (! str_starts_with($trimmed, '{') || ! str_ends_with($trimmed, '}')) {
            throw new InvalidArgumentException('Schema must start with { and end with }.');
        }

        $body = trim(substr($trimmed, 1, -1));

        $fields = $this->parseFields($body);

        return [
            'type' => 'object',
            'fields' => $fields,
        ];
    }

    private function parseFields(string $body): array
    {
        $parts = $this->splitTopLevel($body, ';');
        $result = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            [$name, $rest] = $this->extractNameAndType($part);
            $result[$name] = $rest;
        }

        return $result;
    }

    private function extractNameAndType(string $part): array
    {
        if (! preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)(\\?)?\\s*:\\s*(.+)$/', $part, $matches)) {
            throw new InvalidArgumentException("Invalid field definition: $part");
        }

        $name = $matches[1];
        $optional = $matches[2] === '?';
        $typeExpr = trim($matches[3]);

        $schema = $this->parseType($typeExpr);
        $schema['required'] = ! $optional;

        return [$name, $schema];
    }

    private function parseType(string $expr): array
    {
        if ($this->isStringUnion($expr)) {
            return ['type' => 'enum', 'values' => $this->parseEnumValues($expr)];
        }

        if ($this->isArrayType($expr)) {
            $inner = substr($expr, 0, -2);
            return [
                'type' => 'array',
                'items' => $this->parseType($inner),
            ];
        }

        if ($expr === 'string' || $expr === 'number' || $expr === 'boolean') {
            return ['type' => $expr];
        }

        if (str_starts_with($expr, '{') && str_ends_with($expr, '}')) {
            $inner = trim(substr($expr, 1, -1));

            return [
                'type' => 'object',
                'fields' => $this->parseFields($inner),
            ];
        }

        throw new InvalidArgumentException("Unsupported type expression: $expr");
    }

    private function isArrayType(string $expr): bool
    {
        return str_ends_with($expr, '[]');
    }

    private function isStringUnion(string $expr): bool
    {
        return preg_match('/^\"[^\"]+\"(\\s*\\|\\s*\"[^\"]+\")+$/', $expr) === 1;
    }

    private function parseEnumValues(string $expr): array
    {
        return collect($this->splitTopLevel($expr, '|'))
            ->map(fn ($item) => trim($item, " \\\"'"))
            ->filter()
            ->values()
            ->all();
    }

    private function splitTopLevel(string $input, string $delimiter): array
    {
        $result = [];
        $depth = 0;
        $buffer = '';
        $inString = false;

        $len = strlen($input);
        for ($i = 0; $i < $len; $i++) {
            $char = $input[$i];

            if ($char === '"' && ($i === 0 || $input[$i - 1] !== '\\')) {
                $inString = ! $inString;
            }

            if (! $inString) {
                if ($char === '{') {
                    $depth++;
                } elseif ($char === '}') {
                    $depth--;
                }
            }

            if ($depth === 0 && ! $inString && substr($input, $i, strlen($delimiter)) === $delimiter) {
                $result[] = $buffer;
                $buffer = '';
                $i += strlen($delimiter) - 1;
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $result[] = $buffer;
        }

        return $result;
    }
}
