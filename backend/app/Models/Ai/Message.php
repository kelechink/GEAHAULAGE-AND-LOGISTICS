<?php

declare(strict_types=1);

namespace App\Models\Ai;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ai_conversation_id
 * @property string $role visitor, assistant, staff or system
 * @property string $body
 * @property int|null $user_id
 * @property string|null $provider
 * @property array<string, mixed>|null $meta
 * @property Carbon $created_at
 * @property-read User|null $user
 */
class Message extends Model
{
    protected $table = 'ai_messages';

    protected $fillable = ['ai_conversation_id', 'role', 'body', 'user_id', 'provider', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'ai_conversation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array{id: int, role: string, body: string, at: string, meta: array<string, mixed>} */
    public function toPublic(): array
    {
        return ['id' => $this->id, 'role' => $this->role, 'body' => $this->body, 'at' => $this->created_at->toIso8601String(),
            'meta' => array_intersect_key($this->meta ?? [], array_flip(['form', 'quick_replies']))];
    }
}
