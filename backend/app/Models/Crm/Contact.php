<?php

declare(strict_types=1);

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $whatsapp
 * @property string $source
 */
class Contact extends Model
{
    protected $fillable = ['company_id', 'name', 'phone', 'email', 'whatsapp', 'role', 'source', 'consent_marketing'];

    protected function casts(): array
    {
        return ['consent_marketing' => 'boolean'];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<Lead, $this> */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
