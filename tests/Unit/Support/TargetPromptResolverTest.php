<?php

namespace Tests\Unit\Support;

use App\Models\ChainNode;
use App\Models\RunStep;
use App\Support\TargetPromptResolver;
use PHPUnit\Framework\TestCase;

class TargetPromptResolverTest extends TestCase
{
    public function testResolvesSystemPromptFirst(): void
    {
        $resolver = new TargetPromptResolver();

        $id = $resolver->fromMessagesConfig([
            ['role' => 'user', 'prompt_version_id' => 2],
            ['role' => 'system', 'prompt_version_id' => 5],
        ]);

        $this->assertSame(5, $id);
    }

    public function testResolvesUserPromptWhenNoSystem(): void
    {
        $resolver = new TargetPromptResolver();

        $id = $resolver->fromMessagesConfig([
            ['role' => 'user', 'prompt_version_id' => 3],
        ]);

        $this->assertSame(3, $id);
    }

    public function testFromRunStep(): void
    {
        $resolver = new TargetPromptResolver();
        $node = new ChainNode();
        $node->setAttribute('messages_config', [
            ['role' => 'user', 'prompt_version_id' => 7],
        ]);

        $step = new RunStep();
        $step->setRelation('chainNode', $node);

        $this->assertSame(7, $resolver->fromRunStep($step));
    }
}
