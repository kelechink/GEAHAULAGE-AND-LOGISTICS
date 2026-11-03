<?php

declare(strict_types=1);

namespace App\Models\Privacy;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $type
 * @property string $subject_masked
 * @property array<string, mixed> $summary
 * @property int|null $handled_by
 * @property Carbon $created_at
 * @property-read User|null $handler
 */
class PrivacyRequest extends Model
{
    protected $fillable = ['type', 'subject_masked', 'subject_hash', 'summary', 'handled_by'];

    protected function casts(): array
    {
        return ['summary' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
