<?php

namespace Tests\Unit\Services\Runs;

use App\Services\Llm\LlmResponseDto;
use App\Services\Runs\SchemaValidator;
use Tests\TestCase;

class SchemaValidatorTest extends TestCase
{
    public function testParsesWithoutSchema(): void
    {
        $validator = new SchemaValidator();
        $response = new LlmResponseDto('{"foo":"bar"}', usage: [], raw: []);

        [$parsed, $errors] = $validator->parseAndValidate($response, null);

        $this->assertSame(['foo' => 'bar'], $parsed);
        $this->assertSame([], $errors);
    }

    public function testValidatesObjectSchemaAndReturnsErrors(): void
    {
        $validator = new SchemaValidator();
        $response = new LlmResponseDto('{"foo":"ok"}', usage: [], raw: []);
        $schema = [
            'type' => 'object',
            'fields' => [
                'foo' => ['type' => 'string', 'required' => true],
                'bar' => ['type' => 'number', 'required' => true],
            ],
        ];

        [$parsed, $errors] = $validator->parseAndValidate($response, $schema);

        $this->assertSame(['foo' => 'ok'], $parsed);
        $this->assertContains('response.bar is required.', $errors);
    }

    public function testUnknownTypeProducesError(): void
    {
        $validator = new SchemaValidator();
        $response = new LlmResponseDto('{"foo":"ok"}', usage: [], raw: []);
        $schema = ['type' => 'weird'];

        [, $errors] = $validator->parseAndValidate($response, $schema);

        $this->assertContains('response has unsupported schema type: weird.', $errors);
    }

    public function testEnumMustUseAllowedValues(): void
    {
        $validator = new SchemaValidator();
        $response = new LlmResponseDto('"blue"', usage: [], raw: []);
        $schema = ['type' => 'enum', 'values' => ['red', 'green']];

        [, $errors] = $validator->parseAndValidate($response, $schema);

        $this->assertContains('response must be one of: red, green.', $errors);
    }

    public function testInvalidJsonIsReported(): void
    {
        $validator = new SchemaValidator();
        $response = new LlmResponseDto('{oops}', usage: [], raw: []);

        [, $errors] = $validator->parseAndValidate($response, ['type' => 'string']);

        $this->assertContains('Response is not valid JSON', $errors);
    }
}
