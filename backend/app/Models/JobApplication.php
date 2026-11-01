<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $phone
 * @property string $role
 * @property int|null $years_experience
 * @property string|null $licence_class
 * @property string|null $about
 * @property string $status
 */
class JobApplication extends Model
{
    protected $fillable = ['name', 'phone', 'role', 'years_experience', 'licence_class', 'about', 'status'];
}
