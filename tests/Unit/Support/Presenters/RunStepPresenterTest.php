<?php

namespace Tests\Unit\Support\Presenters;

use App\Models\ChainNode;
use App\Models\Feedback;
use App\Models\ProviderCredential;
use App\Models\PromptVersion;
use App\Models\RunStep;
use App\Support\Presenters\RunStepPresenter;
use App\Support\TargetPromptResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class RunStepPresenterTest extends TestCase
{
    public function testPresentsStepWithTargetPrompt(): void
    {
        $resolver = new TargetPromptResolver();
        $presenter = new RunStepPresenter($resolver);

        $credential = new ProviderCredential();
        $credential->setAttribute('provider', 'openai');
        $credential->setAttribute('name', 'OpenAI Prod');

        $node = new ChainNode();
        $node->setAttribute('id', 1);
        $node->setAttribute('name', 'Step One');
        $node->setAttribute('model_name', 'gpt-4');
        $node->setAttribute('messages_config', [
            ['role' => 'system', 'prompt_version_id' => 10],
        ]);
        $node->setRelation('providerCredential', $credential);

        $step = new RunStep();
        $step->setAttribute('id', 5);
        $step->setAttribute('order_index', 0);
        $step->setAttribute('status', 'success');
        $step->setAttribute('request_payload', []);
        $step->setAttribute('response_raw', []);
        $step->setAttribute('parsed_output', []);
        $step->setAttribute('tokens_in', 1);
        $step->setAttribute('tokens_out', 2);
        $step->setAttribute('duration_ms', 100);
        $step->setAttribute('created_at', Carbon::parse('2024-01-01'));
        $step->setRelation('chainNode', $node);
        $step->setRelation('feedback', collect([new Feedback()]));

        $promptVersion = new PromptVersion();
        $promptVersion->setAttribute('id', 10);
        $promptVersion->setAttribute('prompt_template_id', 200);

        $promptVersions = new Collection([$promptVersion->id => $promptVersion]);

        $presented = $presenter->present($step, $promptVersions);

        $this->assertSame(10, $presented['target_prompt_version_id']);
        $this->assertSame(200, $presented['target_prompt_template_id']);
        $this->assertSame('Step One', $presented['chain_node']['name']);
    }
}
