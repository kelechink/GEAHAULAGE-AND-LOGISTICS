<?php

declare(strict_types=1);

namespace App\Domain\Ai;

use App\Models\Ai\Conversation;
use App\Models\Ai\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Staff side of website chats (PRD 12.4): take over, reply, hand back, close. */
final class Inbox
{
    public function takeOver(Conversation $c, User $by): void
    {
        if ($c->status === 'human' && $c->assigned_user_id === $by->id) {
            return;
        }
        $c->forceFill(['status' => 'human', 'assigned_user_id' => $by->id])->save();
        $c->messages()->create(['role' => 'system', 'body' => 'A GEA team member has joined the chat.', 'user_id' => $by->id]);
    }

    public function reply(Conversation $c, User $by, string $text): Message
    {
        $text = trim($text);
        if ($text === '') {
            throw new InvalidArgumentException('Write a reply first.');
        }
        $this->takeOver($c, $by);
        $c->forceFill(['last_message_at' => now()])->save();

        /** @var Message */
        return $c->messages()->create(['role' => 'staff', 'body' => mb_substr($text, 0, 2000), 'user_id' => $by->id]);
    }

    public function handBack(Conversation $c, User $by): void
    {
        $c->forceFill(['status' => 'bot', 'assigned_user_id' => null])->save();
        $c->messages()->create(['role' => 'system', 'body' => 'The GEA assistant is back. Ask anything, or call us.', 'user_id' => $by->id]);
    }

    public function close(Conversation $c, User $by): void
    {
        $c->forceFill(['status' => 'closed'])->save();
        $c->messages()->create(['role' => 'system', 'body' => 'This chat was closed by GEA. Thank you for contacting us.', 'user_id' => $by->id]);
    }

    /**
     * Requests, failures and cache hits for today, per provider (PRD 12.1: daily usage counter in admin).
     *
     * @return list<array<string, mixed>>
     */
    public static function usageToday(): array
    {
        return DB::table('ai_usage')->where('day', now('Africa/Lagos')->toDateString())
            ->get(['provider', 'requests', 'failures', 'tokens', 'cache_hits'])->map(fn ($r): array => (array) $r)->values()->all();
    }
}
