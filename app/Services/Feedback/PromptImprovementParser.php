<?php

namespace App\Services\Feedback;

class PromptImprovementParser
{
    public function parse(string $content): ?array
    {
        $analysis = null;
        $suggestion = null;

        $decoded = $this->decodeImprovementPayload($content);

        if (is_array($decoded)) {
            $analysis = $decoded['analysis'] ?? null;
            $suggestion = $decoded['improved_prompt']
                ?? $decoded['improved prompt']
                ?? $decoded['improvedPrompt']
                ?? $decoded['suggestion']
                ?? null;
        }

        if (! $suggestion && ! $analysis) {
            $suggestion = $content;
        }

        if ($suggestion === null && $analysis === null) {
            return null;
        }

        return [
            'suggestion' => $suggestion,
            'analysis' => $analysis,
        ];
    }

    private function decodeImprovementPayload(string $content): ?array
    {
        $trimmed = trim($content);

        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $trimmed, $matches)) {
            $trimmed = trim($matches[1]);
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $candidate = substr($trimmed, $start, $end - $start + 1);
            $decoded = json_decode($candidate, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
