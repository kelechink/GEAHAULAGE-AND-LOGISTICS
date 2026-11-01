<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Common towns and checkpoints on GEA's routes, for fast manual updates (PRD 8.3).
 *
 * @property int $id
 * @property string $name
 * @property string|null $state
 * @property string $lat
 * @property string $lng
 */
class Place extends Model
{
    protected $fillable = ['name', 'state', 'lat', 'lng', 'is_checkpoint'];

    protected function casts(): array
    {
        return ['is_checkpoint' => 'boolean'];
    }

    public function label(): string
    {
        return $this->state ? "{$this->name}, {$this->state}" : $this->name;
    }
}
