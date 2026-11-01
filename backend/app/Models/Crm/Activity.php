<?php

declare(strict_types=1);

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A CRM touchpoint (call, email, note, form submission). Not the audit log.
 *
 * @property int $id
 * @property string $type
 * @property string $body
 */
class Activity extends Model
{
    protected $table = 'activities';

    protected $fillable = ['lead_id', 'contact_id', 'type', 'body', 'user_id', 'at'];

    protected function casts(): array
    {
        return ['at' => 'datetime'];
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
