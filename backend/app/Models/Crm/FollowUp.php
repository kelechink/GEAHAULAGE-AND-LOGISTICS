<?php

declare(strict_types=1);

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lead_id
 * @property Carbon $due_at
 * @property string $status
 */
class FollowUp extends Model
{
    protected $fillable = ['lead_id', 'due_at', 'assigned_to', 'status', 'note'];

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime'];
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
