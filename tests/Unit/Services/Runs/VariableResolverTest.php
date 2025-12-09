<?php

namespace Tests\Unit\Services\Runs;

use App\Services\Runs\VariableResolver;
use Tests\TestCase;

class VariableResolverTest extends TestCase
{
    public function testResolvesFromInputByDefault(): void
    {
        $resolver = new VariableResolver();

        $result = $resolver->resolve(
            [['name' => 'topic']],
            [],
            ['topic' => 'laravel'],
            []
        );

        $this->assertSame(['topic' => 'laravel'], $result);
    }

    public function testResolvesFromPreviousStep(): void
    {
        $resolver = new VariableResolver();

        $result = $resolver->resolve(
            [['name' => 'city']],
            [
                'city' => [
                    'source' => 'previous_step',
                    'step_key' => 'first_step',
                    'path' => 'parsed_output.address.city',
                ],
            ],
            [],
            [
                'first_step' => [
                    'parsed_output' => [
                        'address' => ['city' => 'Paris'],
                    ],
                ],
            ]
        );

        $this->assertSame(['city' => 'Paris'], $result);
    }

    public function testResolvesConstant(): void
    {
        $resolver = new VariableResolver();

        $result = $resolver->resolve(
            [['name' => 'lang']],
            [
                'lang' => [
                    'source' => 'constant',
                    'value' => 'en',
                ],
            ],
            [],
            []
        );

        $this->assertSame(['lang' => 'en'], $result);
    }
}
