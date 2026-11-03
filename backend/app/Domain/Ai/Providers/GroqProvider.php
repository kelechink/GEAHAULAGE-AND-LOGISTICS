<?php

declare(strict_types=1);

namespace App\Domain\Ai\Providers;

use App\Domain\Ai\AiProvider;
use App\Domain\Ai\AiResponse;
use App\Domain\Ai\AiUnavailable;
use App\Domain\Ai\ToolCall;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Groq, free tier, OpenAI-compatible chat API with an open model (PRD 12.1, fallback). Key in GROQ_API_KEY. */
final class GroqProvider implements AiProvider
{
    public function name(): string
    {
        return 'groq';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.groq.key'));
    }

    public function chat(array $messages, array $tools = [], array $options = []): AiResponse
    {
        if (! $this->isConfigured()) {
            throw new AiUnavailable('Groq is not configured.');
        }

        $out = [];
        foreach ($messages as $m) {
            $row = ['role' => (string) $m['role'], 'content' => (string) ($m['content'] ?? '')];
            /** @var list<ToolCall> $calls */
            $calls = $m['tool_calls'] ?? [];
            if ($calls !== []) {
                $row['tool_calls'] = array_map(fn (ToolCall $c): array => [
                    'id' => $c->id, 'type' => 'function', 'function' => ['name' => $c->name, 'arguments' => json_encode((object) $c->arguments)],
                ], $calls);
            }
            if ($m['role'] === 'tool') {
                $row['tool_call_id'] = (string) $m['tool_call_id'];
            }
            $out[] = $row;
        }

        $body = [
            'model' => (string) config('services.groq.model'),
            'messages' => $out,
            'temperature' => $options['temperature'] ?? 0.3,
            'max_tokens' => $options['max_tokens'] ?? 512,
        ];
        if ($tools !== []) {
            $body['tools'] = array_map(fn (array $t): array => ['type' => 'function', 'function' => $t], $tools);
        }

        try {
            $res = Http::timeout(25)->acceptJson()->withToken((string) config('services.groq.key'))
                ->post('https://api.groq.com/openai/v1/chat/completions', $body);
        } catch (ConnectionException $e) {
            throw new AiUnavailable('Groq unreachable: '.$e->getMessage());
        }

        if ($res->status() === 429) {
            throw new AiUnavailable('Groq rate limit reached.', rateLimited: true);
        }
        if ($res->failed()) {
            throw new AiUnavailable('Groq error '.$res->status());
        }

        $calls = [];
        foreach ((array) $res->json('choices.0.message.tool_calls', []) as $c) {
            $args = json_decode((string) ($c['function']['arguments'] ?? '{}'), true);
            $calls[] = new ToolCall((string) ($c['id'] ?? uniqid('q-')), (string) $c['function']['name'], is_array($args) ? $args : []);
        }
        $text = trim((string) $res->json('choices.0.message.content', ''));
        if ($text === '' && $calls === []) {
            throw new AiUnavailable('Groq returned no content.');
        }

        return new AiResponse($text, $calls, $this->name(), (int) $res->json('usage.total_tokens', 0));
    }
}
