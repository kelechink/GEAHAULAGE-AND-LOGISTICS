<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 * @property string $description
 * @property bool $is_open
 * @property int $sort
 */
class CareerRole extends Model
{
    protected $fillable = ['title', 'description', 'is_open', 'sort'];

    protected function casts(): array
    {
        return ['is_open' => 'boolean', 'sort' => 'integer'];
    }

    /** @param Builder<CareerRole> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->where('is_open', true)->orderBy('sort');
    }
}
