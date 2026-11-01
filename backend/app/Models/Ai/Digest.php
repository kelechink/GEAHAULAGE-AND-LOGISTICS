<?php

declare(strict_types=1);

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $day
 * @property string $summary
 * @property array<string, mixed> $facts
 * @property string|null $provider
 */
class Digest extends Model
{
    protected $table = 'ai_digests';

    protected $fillable = ['day', 'summary', 'facts', 'provider'];

    protected function casts(): array
    {
        return ['day' => 'date', 'facts' => 'array'];
    }
}
