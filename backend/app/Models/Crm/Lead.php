<?php

declare(strict_types=1);

namespace App\Models\Crm;

use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Models\Customer;
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
 * @property Carbon|null $first_contacted_at
 * @property Carbon|null $won_at
 * @property Carbon|null $lost_at
 * @property int|null $estimated_value_kobo
 * @property int|null $owner_user_id
 * @property int|null $customer_id
 * @property string|null $lost_reason
 * @property int|null $intent_score
 * @property-read Contact $contact
 * @property-read Customer|null $customer
 * @property-read User|null $owner
 */
class Lead extends Model
{
    use LogsActivity;

    protected $fillable = [
        'contact_id', 'company_id', 'source', 'service_interest', 'origin', 'destination', 'cargo',
        'tonnage', 'preferred_date', 'estimated_value_kobo', 'stage', 'lost_reason', 'intent_score',
        'owner_user_id', 'ai_summary', 'next_follow_up_at', 'customer_id', 'first_contacted_at', 'won_at', 'lost_at',
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
            'first_contacted_at' => 'datetime',
            'won_at' => 'datetime',
            'lost_at' => 'datetime',
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

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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

    public function isOpen(): bool
    {
        return ! in_array($this->stage, [LeadStage::Won, LeadStage::Lost], true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
