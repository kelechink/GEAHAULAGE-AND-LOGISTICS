<?php

declare(strict_types=1);

namespace App\Domain\Ai;

use RuntimeException;

/** Rate-limited, unreachable, misconfigured or a malformed reply. The gateway then tries the next provider. */
final class AiUnavailable extends RuntimeException
{
    public function __construct(string $message, public readonly bool $rateLimited = false)
    {
        parent::__construct($message);
    }
}
