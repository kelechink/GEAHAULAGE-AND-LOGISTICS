<?php

declare(strict_types=1);

namespace App\Models\Ai;

use App\Models\Crm\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A website chat (PRD 12.3, 12.4). status: bot → needs_human → human → closed.
 *
 * @property int $id
 * @property string $token
 * @property string $status
 * @property string|null $page
 * @property string|null $visitor_name
 * @property string|null $visitor_phone
 * @property string|null $visitor_email
 * @property int|null $lead_id
 * @property int|null $assigned_user_id
 * @property string|null $handoff_reason
 * @property string|null $summary
 * @property int $turns
 * @property Carbon|null $last_message_at
 * @property-read Lead|null $lead
 * @property-read User|null $assignee
 */
class Conversation extends Model
{
    public const STATUSES = ['bot' => 'Assistant', 'needs_human' => 'Needs a person', 'human' => 'Staff replying', 'closed' => 'Closed'];

    protected $table = 'ai_conversations';

    protected $fillable = ['token', 'status', 'ip_hash', 'page', 'visitor_name', 'visitor_phone', 'visitor_email', 'lead_id',
        'assigned_user_id', 'handoff_reason', 'summary', 'turns', 'last_message_at'];

    protected $attributes = ['status' => 'bot', 'turns' => 0];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime', 'turns' => 'integer'];
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'ai_conversation_id')->orderBy('id');
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function isHuman(): bool
    {
        return in_array($this->status, ['needs_human', 'human'], true);
    }

    /** Plain transcript for the CRM lead and staff emails. */
    public function transcript(): string
    {
        return $this->messages->map(fn (Message $m): string => ucfirst($m->role).': '.$m->body)->implode("\n");
    }
}
