<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property bool $enabled
 * @property array<string, mixed>|null $settings
 * @property string|null $secret
 * @property Carbon|null $last_polled_at
 */
class TrackingConnector extends Model
{
    protected $fillable = ['name', 'enabled', 'settings', 'secret', 'last_polled_at'];

    protected $hidden = ['settings', 'secret'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'settings' => 'encrypted:array', 'secret' => 'encrypted', 'last_polled_at' => 'datetime'];
    }
}
