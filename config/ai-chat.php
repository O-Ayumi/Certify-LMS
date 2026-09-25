<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('AI_CHAT_ENABLED', true),
    'daily_limit' => (int) env('AI_CHAT_DAILY_LIMIT', 20),
    'max_history' => (int) env('AI_CHAT_MAX_HISTORY', 20),
    'title_generation_enabled' => (bool) env('AI_CHAT_TITLE_GENERATION_ENABLED', true),
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.8-flash'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 30),
        'system_prompt' => env('GEMINI_SYSTEM_PROMPT', ''),
    ],
];
