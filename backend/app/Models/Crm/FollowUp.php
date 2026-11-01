<?php

declare(strict_types=1);

namespace App\Models\Crm;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lead_id
 * @property Carbon $due_at
 * @property string $status
 * @property int|null $assigned_to
 * @property string|null $note
 * @property-read Lead $lead
 * @property-read User|null $assignee
 */
class FollowUp extends Model
{
    protected $fillable = ['lead_id', 'due_at', 'assigned_to', 'status', 'note'];

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @param Builder<FollowUp> $query */
    public function scopeOverdue(Builder $query): void
    {
        $query->where('status', 'open')->where('due_at', '<', now());
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
