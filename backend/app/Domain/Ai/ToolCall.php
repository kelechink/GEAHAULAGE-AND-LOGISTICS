<?php

declare(strict_types=1);

namespace App\Domain\Ai;

final class ToolCall
{
    /**
     * @param  array<string, mixed>  $arguments
     * @param  string|null  $signature  Gemini's thought signature: must be sent back with the call on the next turn
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $arguments,
        public readonly ?string $signature = null,
    ) {}
}
