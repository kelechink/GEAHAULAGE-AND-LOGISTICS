<?php

declare(strict_types=1);

namespace App\Domain\Ai;

final class AiResponse
{
    /** @param  list<ToolCall>  $toolCalls */
    public function __construct(
        public readonly string $text,
        public readonly array $toolCalls,
        public readonly string $provider,
        public readonly int $tokens = 0,
    ) {}

    public function wantsTools(): bool
    {
        return $this->toolCalls !== [];
    }
}
