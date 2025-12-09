<?php

namespace App\Services\Runs;

use App\Models\ChainNode;
use App\Models\Run;
use App\Services\Llm\LlmService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RunStepRunner
{
    public function __construct(
        private LlmService $llmService,
        private MessageBuilder $messageBuilder,
        private SchemaValidator $schemaValidator,
        private RunStepRecorder $runStepRecorder
    ) {
    }

    /**
     * @param Collection<int, ChainNode> $nodes
     * @param array{by_id: Collection<int, \App\Models\PromptVersion>, by_template: Collection<int, \App\Models\PromptVersion>} $promptVersions
     */
    public function runSteps(Run $run, Collection $nodes, array $promptVersions): array
    {
        $stepOutputs = [];
        $totalTokensIn = 0;
        $totalTokensOut = 0;
        $failed = false;

        foreach ($nodes as $node) {
            $stepStart = microtime(true);
            $messages = $this->messageBuilder->build($node, $promptVersions, $run->input ?? [], $stepOutputs);
            $params = $node->model_params ?? [];

            $responseDto = null;
            $validationErrors = [];
            $parsedOutput = null;
            $status = 'success';

            try {
                if (! $node->providerCredential) {
                    throw new \RuntimeException('Provider credential is missing for node '.$node->id);
                }

                $responseDto = $this->llmService->call(
                    $node->providerCredential,
                    $node->model_name,
                    $messages,
                    $params
                );

                [$parsedOutput, $validationErrors] = $this->schemaValidator->parseAndValidate(
                    $responseDto,
                    $node->output_schema
                );

                if ($validationErrors && $node->stop_on_validation_error) {
                    $status = 'failed';
                    $failed = true;
                }
            } catch (\Throwable $e) {
                $messageRoles = collect($messages)->map(fn ($msg) => $msg['role'] ?? 'user')->all();

                Log::error('RunStepRunner: step failed', [
                    'run_id' => $run->id,
                    'chain_node_id' => $node->id,
                    'node_name' => $node->name,
                    'provider' => $node->providerCredential?->provider,
                    'model' => $node->model_name,
                    'message_roles' => $messageRoles,
                    'error' => $e->getMessage(),
                ]);

                $validationErrors[] = 'LLM call failed: '.$e->getMessage();
                $status = 'failed';
                $failed = true;
            }

            $durationMs = (int) ((microtime(true) - $stepStart) * 1000);
            $totalTokensIn += $responseDto?->tokensIn() ?? 0;
            $totalTokensOut += $responseDto?->tokensOut() ?? 0;

            $this->runStepRecorder->record(
                $run,
                $node,
                $messages,
                $params,
                $responseDto,
                $parsedOutput,
                $validationErrors,
                $status,
                $durationMs
            );

            $stepKey = $this->stepKey($node);
            $stepOutputs[$stepKey] = [
                'parsed_output' => $parsedOutput,
                'raw_output' => $responseDto ? $responseDto->content : null,
                'response_raw' => $responseDto ? $responseDto->raw : null,
            ];

            if ($failed) {
                break;
            }
        }

        return [
            'total_tokens_in' => $totalTokensIn,
            'total_tokens_out' => $totalTokensOut,
            'failed' => $failed,
        ];
    }

    private function stepKey(ChainNode $node): string
    {
        $key = Str::slug($node->name, '_');

        return $key ?: 'step_'.$node->id;
    }
}
