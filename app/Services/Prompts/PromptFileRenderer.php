<?php

namespace App\Services\Prompts;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PromptFileRenderer
{
    private const ALLOWED_EXTENSIONS = ['txt', 'xml'];

    /**
     * Render a prompt file from resources/prompts by replacing {{ variables }}.
     *
     * @param  string  $templateName  Filename with or without extension (e.g. "welcome", "welcome.txt").
     * @param  array<string, mixed>  $variables
     */
    public function render(string $templateName, array $variables = []): string
    {
        $path = $this->resolvePath($templateName);

        if (! File::isFile($path)) {
            throw new InvalidArgumentException(sprintf('Prompt file not found: %s', $templateName));
        }

        $contents = File::get($path);

        if ($contents === false) {
            throw new InvalidArgumentException(sprintf('Failed to read prompt file: %s', $templateName));
        }

        return $this->replaceVariables($contents, $variables);
    }

    private function resolvePath(string $templateName): string
    {
        $name = trim($templateName);

        if ($name === '' || str_contains($name, '..') || str_contains($name, '/')) {
            throw new InvalidArgumentException('Invalid prompt template name.');
        }

        $extension = File::extension($name);
        $baseName = $extension ? File::name($name) : $name;

        if ($extension && ! in_array(strtolower($extension), self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException('Unsupported prompt file extension.');
        }

        $dir = resource_path('prompts');

        if ($extension) {
            return Str::finish($dir, DIRECTORY_SEPARATOR).$baseName.'.'.$extension;
        }

        foreach (self::ALLOWED_EXTENSIONS as $allowed) {
            $candidate = Str::finish($dir, DIRECTORY_SEPARATOR).$baseName.'.'.$allowed;
            if (File::isFile($candidate)) {
                return $candidate;
            }
        }

        return Str::finish($dir, DIRECTORY_SEPARATOR).$baseName.'.txt';
    }

    private function replaceVariables(string $contents, array $variables): string
    {
        return preg_replace_callback('/\\{\\{\\s*([a-zA-Z_][a-zA-Z0-9_.]*)\\s*\\}\\}/', function (array $matches) use ($variables): string {
            $key = $matches[1] ?? '';
            $value = Arr::get($variables, $key);

            if (is_array($value)) {
                return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
            }

            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }

            return $value === null ? '' : (string) $value;
        }, $contents);
    }
}
