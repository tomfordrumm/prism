<?php

namespace Tests\Unit;

use App\Support\PromptVariableParser;
use PHPUnit\Framework\TestCase;

class PromptVariableParserTest extends TestCase
{
    public function test_it_extracts_unique_variables(): void
    {
        $content = <<<PROMPT
        Hello {{ topic }}!
        User: {{user.name}} should get {{ topic }} content and {{user.name}} has id {{user.id_1}}.
        Ignore malformed {{ 123 }} and keep {{_flag}}.
        PROMPT;

        $variables = PromptVariableParser::extract($content);

        $this->assertSame(
            ['topic', 'user.name', 'user.id_1', '_flag'],
            $variables
        );
    }
}
