<?php

declare(strict_types=1);

namespace App\Domain\Ai\Providers;

use App\Domain\Ai\AiProvider;
use App\Domain\Ai\AiResponse;
use App\Domain\Ai\AiUnavailable;
use App\Domain\Ai\ToolCall;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** Google Gemini, free tier (PRD 12.1, primary). Key stays server-side in GEMINI_API_KEY. */
final class GeminiProvider implements AiProvider
{
    public function name(): string
    {
        return 'gemini';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.gemini.key'));
    }

    public function chat(array $messages, array $tools = [], array $options = []): AiResponse
    {
        if (! $this->isConfigured()) {
            throw new AiUnavailable('Gemini is not configured.');
        }

        $system = [];
        $contents = [];
        foreach ($messages as $m) {
            $role = (string) $m['role'];
            if ($role === 'system') {
                $system[] = (string) $m['content'];

                continue;
            }
            if ($role === 'tool') {
                $contents[] = ['role' => 'user', 'parts' => [['functionResponse' => [
                    'name' => (string) $m['name'], 'response' => ['result' => json_decode((string) $m['content'], true) ?? (string) $m['content']],
                ]]]];

                continue;
            }
            $parts = [];
            if (filled($m['content'] ?? null)) {
                $parts[] = ['text' => (string) $m['content']];
            }
            /** @var list<ToolCall> $calls */
            $calls = $m['tool_calls'] ?? [];
            foreach ($calls as $call) {
                $parts[] = ['functionCall' => ['name' => $call->name, 'args' => (object) $call->arguments]];
            }
            $contents[] = ['role' => $role === 'assistant' ? 'model' : 'user', 'parts' => $parts];
        }

        $body = [
            'contents' => $contents,
            'generationConfig' => ['temperature' => $options['temperature'] ?? 0.3, 'maxOutputTokens' => $options['max_tokens'] ?? 512],
        ];
        if ($system !== []) {
            $body['systemInstruction'] = ['parts' => [['text' => implode("\n\n", $system)]]];
        }
        if ($tools !== []) {
            $body['tools'] = [['functionDeclarations' => array_map(fn (array $t): array => $t, $tools)]];
        }

        $model = (string) config('services.gemini.model');
        try {
            $res = Http::timeout(25)->acceptJson()
                ->withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", $body);
        } catch (ConnectionException $e) {
            throw new AiUnavailable('Gemini unreachable: '.$e->getMessage());
        }

        if ($res->status() === 429) {
            throw new AiUnavailable('Gemini rate limit reached.', rateLimited: true);
        }
        if ($res->failed()) {
            throw new AiUnavailable('Gemini error '.$res->status());
        }

        $text = '';
        $calls = [];
        foreach ((array) $res->json('candidates.0.content.parts', []) as $part) {
            if (isset($part['text'])) {
                $text .= (string) $part['text'];
            }
            if (isset($part['functionCall']['name'])) {
                $calls[] = new ToolCall('g-'.Str::random(8), (string) $part['functionCall']['name'], (array) ($part['functionCall']['args'] ?? []));
            }
        }
        if ($text === '' && $calls === []) {
            throw new AiUnavailable('Gemini returned no content ('.((string) $res->json('candidates.0.finishReason', 'unknown')).').');
        }

        return new AiResponse(trim($text), $calls, $this->name(), (int) $res->json('usageMetadata.totalTokenCount', 0));
    }
}
