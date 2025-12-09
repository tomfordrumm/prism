<?php

namespace App\Support;

class PromptVariableParser
{
    /**
     * Extract unique variable names from prompt content.
     *
     * Variables are defined as {{ variable }} with variable names matching
     * [a-zA-Z_][a-zA-Z0-9_.]*. Duplicates are removed while preserving the
     * order of first appearance.
     */
    public static function extract(string $content): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z_][a-zA-Z0-9_.]*)\s*\}\}/', $content, $matches);

        $names = $matches[1];

        $seen = [];
        $variables = [];

        foreach ($names as $name) {
            if (isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;
            $variables[] = $name;
        }

        return $variables;
    }
}
