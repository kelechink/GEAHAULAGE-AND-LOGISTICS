<?php

declare(strict_types=1);

namespace App\Domain\Ai;

/**
 * PRD 12.1. Messages use one neutral shape that each adapter maps to its own API:
 *   ['role' => 'system'|'user'|'assistant'|'tool', 'content' => string,
 *    'tool_calls' => list<ToolCall> (assistant only), 'tool_call_id' => string, 'name' => string (tool only)]
 * Tools: ['name' => string, 'description' => string, 'parameters' => JSON schema array].
 */
interface AiProvider
{
    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array{name: string, description: string, parameters: array<string, mixed>}>  $tools
     * @param  array<string, mixed>  $options
     *
     * @throws AiUnavailable when the provider is rate-limited, down or not configured
     */
    public function chat(array $messages, array $tools = [], array $options = []): AiResponse;

    public function name(): string;

    public function isConfigured(): bool;
}
