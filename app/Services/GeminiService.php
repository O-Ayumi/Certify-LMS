<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiService
{
    public function answer(array $messages, array $context = []): array
    {
        $key = config('ai-chat.gemini.api_key');
        if (! $key) {
            throw new RuntimeException('AI configuration unavailable', 503);
        }

        $prompt = config('ai-chat.gemini.system_prompt');
        if ($context) {
            $prompt .= "\n学習コンテキスト:\n".json_encode($context, JSON_UNESCAPED_UNICODE);
        }

        $historyText = collect($messages)->map(fn (array $message): string => ($message['role'] === 'assistant' ? 'AI' : '受講生').': '.$message['content'])->implode("\n\n");

        $contents = [[
            'role' => 'user',
            'parts' => [['text' => $historyText]],
        ]];

        $started = microtime(true);
        $response = Http::timeout(config('ai-chat.gemini.timeout', 30))

            ->withHeaders(['x-goog-api-key' => $key])
            ->post(
                'https://generativelanguage.googleapis.com/v1beta/models/'.config('ai-chat.gemini.model').':generateContent',
                [
                    'system_instruction' => ['parts' => [['text' => $prompt]]],
                    'contents' => $contents,
                ],
            );

        if (! $response->successful()) {
            throw new RuntimeException('Gemini request failed', $response->status());
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        if (! is_string($text) || $text === '') {
            throw new RuntimeException('Empty Gemini response', 502);
        }

        return [
            'content' => $text,
            'elapsed_ms' => (int) ((microtime(true) - $started) * 1000),
        ];
    }
}
