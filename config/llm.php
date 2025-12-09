<?php

return [
    'judge_credential_id' => env('LLM_JUDGE_CREDENTIAL_ID'),
    'judge_model' => env('LLM_JUDGE_MODEL', 'gemini-1.5-flash'),
    'judge_params' => env('LLM_JUDGE_PARAMS') ? json_decode(env('LLM_JUDGE_PARAMS'), true) : [],
    'models' => [
        'openai' => [
            ['id' => 'gpt-4.1', 'name' => 'gpt-4.1', 'display_name' => 'GPT-4.1'],
            ['id' => 'gpt-4.1-mini', 'name' => 'gpt-4.1-mini', 'display_name' => 'GPT-4.1 Mini'],
            ['id' => 'gpt-4o', 'name' => 'gpt-4o', 'display_name' => 'GPT-4o'],
            ['id' => 'gpt-4o-mini', 'name' => 'gpt-4o-mini', 'display_name' => 'GPT-4o mini'],
        ],
        'anthropic' => [
            ['id' => 'claude-3-5-sonnet-20240620', 'name' => 'claude-3-5-sonnet-20240620', 'display_name' => 'Claude 3.5 Sonnet'],
            ['id' => 'claude-3-opus-20240229', 'name' => 'claude-3-opus-20240229', 'display_name' => 'Claude 3 Opus'],
            ['id' => 'claude-3-haiku-20240307', 'name' => 'claude-3-haiku-20240307', 'display_name' => 'Claude 3 Haiku'],
        ],
        'google' => [
            ['id' => 'gemini-1.5-pro', 'name' => 'gemini-1.5-pro', 'display_name' => 'Gemini 1.5 Pro'],
            ['id' => 'gemini-1.5-flash', 'name' => 'gemini-1.5-flash', 'display_name' => 'Gemini 1.5 Flash'],
        ],
    ],
];
