<?php

namespace Tests\Unit\Services\Runs;

use App\Models\ChainNode;
use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use App\Services\Runs\MessageBuilder;
use App\Services\Runs\VariableResolver;
use Tests\TestCase;

class MessageBuilderTest extends TestCase
{
    public function testBuildsMessageWithResolvedVariables(): void
    {
        $builder = new MessageBuilder(new VariableResolver());
        $node = new ChainNode();
        $node->setAttribute('messages_config', [
            [
                'role' => 'user',
                'prompt_version_id' => 1,
                'variables' => [
                    'name' => ['source' => 'input'],
                    'city' => [
                        'source' => 'previous_step',
                        'step_key' => 'step_one',
                        'path' => 'parsed_output.city',
                    ],
                ],
            ],
        ]);

        $promptTemplate = new PromptTemplate();
        $promptTemplate->setAttribute('variables', [
            ['name' => 'name'],
            ['name' => 'city'],
        ]);

        $promptVersion = new PromptVersion();
        $promptVersion->setAttribute('id', 1);
        $promptVersion->setAttribute('content', 'Hello {{ name }} from {{ city }}');
        $promptVersion->setRelation('promptTemplate', $promptTemplate);

        $promptVersions = [
            'by_id' => collect([$promptVersion->id => $promptVersion]),
            'by_template' => collect(),
        ];

        $input = ['name' => 'Alice'];
        $stepContext = [
            'step_one' => [
                'parsed_output' => [
                    'city' => 'Paris',
                ],
            ],
        ];

        $messages = $builder->build($node, $promptVersions, $input, $stepContext);

        $this->assertCount(1, $messages);
        $this->assertSame('user', $messages[0]['role']);
        $this->assertSame('Hello Alice from Paris', $messages[0]['content']);
    }
}
