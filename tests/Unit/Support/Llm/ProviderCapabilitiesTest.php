<?php

namespace Tests\Unit\Support\Llm;

use App\Support\Llm\ProviderCapabilities;
use PHPUnit\Framework\TestCase;

class ProviderCapabilitiesTest extends TestCase
{
    public function testDefaultsAndKnownProviders(): void
    {
        $capabilities = new ProviderCapabilities();

        $openai = $capabilities->for('openai');
        $this->assertTrue($openai['supports_chat']);
        $this->assertTrue($openai['supports_model_listing']);

        $unknown = $capabilities->for('other');
        $this->assertFalse($unknown['supports_chat']);
        $this->assertFalse($unknown['supports_model_listing']);
    }
}
