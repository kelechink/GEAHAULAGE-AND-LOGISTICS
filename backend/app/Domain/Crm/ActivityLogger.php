<?php

declare(strict_types=1);

namespace App\Domain\Crm;

use App\Models\Crm\Activity;
use App\Models\Crm\Lead;
use App\Models\User;

/**
 * Logs a touchpoint against a lead. A call, email, WhatsApp or meeting counts as first contact.
 */
final class ActivityLogger
{
    public const CONTACT_TYPES = ['call', 'email', 'whatsapp', 'meeting'];

    public const TYPES = [
        'call' => 'Call',
        'email' => 'Email',
        'whatsapp' => 'WhatsApp',
        'meeting' => 'Meeting',
        'note' => 'Note',
    ];

    public function log(Lead $lead, string $type, string $body, ?User $by = null): Activity
    {
        /** @var Activity $activity */
        $activity = $lead->activities()->create([
            'contact_id' => $lead->contact_id,
            'type' => $type,
            'body' => $body,
            'user_id' => $by?->id,
            'at' => now(),
        ]);

        if (in_array($type, self::CONTACT_TYPES, true) && $lead->first_contacted_at === null) {
            $lead->first_contacted_at = now();
            $lead->save();
        }

        return $activity;
    }
}
