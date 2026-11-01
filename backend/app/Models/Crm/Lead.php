<?php

declare(strict_types=1);

namespace App\Models\Crm;

use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $contact_id
 * @property int|null $company_id
 * @property LeadSource $source
 * @property string|null $service_interest
 * @property string|null $origin
 * @property string|null $destination
 * @property string|null $cargo
 * @property LeadStage $stage
 * @property Carbon|null $next_follow_up_at
 * @property-read Contact $contact
 */
class Lead extends Model
{
    use LogsActivity;

    protected $fillable = [
        'contact_id', 'company_id', 'source', 'service_interest', 'origin', 'destination', 'cargo',
        'tonnage', 'preferred_date', 'estimated_value_kobo', 'stage', 'lost_reason', 'intent_score',
        'owner_user_id', 'ai_summary', 'next_follow_up_at',
    ];

    protected $attributes = [
        'stage' => 'new',
    ];

    protected function casts(): array
    {
        return [
            'source' => LeadSource::class,
            'stage' => LeadStage::class,
            'preferred_date' => 'date',
            'next_follow_up_at' => 'datetime',
            'estimated_value_kobo' => 'integer',
            'intent_score' => 'integer',
        ];
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return HasMany<Activity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /** @return HasMany<FollowUp, $this> */
    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
