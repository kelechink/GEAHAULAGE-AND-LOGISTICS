<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string $short_title
 * @property string $summary
 * @property string $body
 * @property list<string>|null $highlights
 * @property string $media
 * @property bool $is_active
 * @property int $sort
 */
class Service extends Model
{
    /** Media keys the website knows how to show for a service. */
    public const MEDIA = [
        'hero-truck' => 'Truck at the yard (video)',
        'truck-vehicle' => 'Trailer wheels (video)',
        'people' => 'Driver (video)',
        'fleet-truck' => 'Branded truck (image)',
        'fleet-boxes' => 'Branded boxes (image)',
    ];

    protected $fillable = ['slug', 'title', 'short_title', 'summary', 'body', 'highlights', 'media', 'is_active', 'sort'];

    protected function casts(): array
    {
        return ['highlights' => 'array', 'is_active' => 'boolean', 'sort' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @param Builder<Service> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort');
    }

    public function isVideo(): bool
    {
        return ! str_starts_with($this->media, 'fleet-');
    }
}
