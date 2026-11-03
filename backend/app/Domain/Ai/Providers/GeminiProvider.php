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
                // Gemini 3+ "thinking" models reject a tool result unless the call's thought signature comes back with it.
                $parts[] = ['functionCall' => ['name' => $call->name, 'args' => (object) $call->arguments]]
                    + ($call->signature !== null ? ['thoughtSignature' => $call->signature] : []);
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

        // Free-tier models get overloaded (503) or retired for new keys (404); try the next model on the same key.
        $models = array_values(array_unique(array_filter(array_merge(
            [(string) config('services.gemini.model')],
            array_map('trim', explode(',', (string) config('services.gemini.fallback_models'))),
        ))));
        $errors = [];
        $res = null;
        foreach ($models as $model) {
            try {
                $try = Http::timeout(25)->acceptJson()
                    ->withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
                    ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", $body);
            } catch (ConnectionException $e) {
                $errors[] = "{$model}: unreachable";

                continue;
            }
            if (in_array($try->status(), [404, 429, 500, 503], true)) {
                $errors[] = "{$model}: ".$try->status();

                continue;
            }
            $res = $try;
            break;
        }
        if ($res === null) {
            $limited = $errors !== [] && collect($errors)->every(fn (string $e): bool => str_ends_with($e, '429'));
            throw new AiUnavailable('Gemini unavailable ('.implode(', ', $errors).')', rateLimited: $limited);
        }
        if ($res->failed()) {
            throw new AiUnavailable('Gemini error '.$res->status().': '.mb_substr((string) $res->json('error.message', ''), 0, 200));
        }

        $text = '';
        $calls = [];
        foreach ((array) $res->json('candidates.0.content.parts', []) as $part) {
            if (isset($part['text'])) {
                $text .= (string) $part['text'];
            }
            if (isset($part['functionCall']['name'])) {
                $calls[] = new ToolCall('g-'.Str::random(8), (string) $part['functionCall']['name'], (array) ($part['functionCall']['args'] ?? []),
                    isset($part['thoughtSignature']) ? (string) $part['thoughtSignature'] : null);
            }
        }
        if ($text === '' && $calls === []) {
            throw new AiUnavailable('Gemini returned no content ('.((string) $res->json('candidates.0.finishReason', 'unknown')).').');
        }

        return new AiResponse(trim($text), $calls, $this->name(), (int) $res->json('usageMetadata.totalTokenCount', 0));
    }
}
