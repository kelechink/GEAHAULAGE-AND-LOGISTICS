<?php

declare(strict_types=1);

namespace App\Domain\Ai;

use App\Models\Ai\Conversation;
use App\Models\Crm\Lead;

/**
 * Rule-based intent score 0–100 for chat leads (PRD 12.4). Computed by Laravel, not the AI, so it is
 * explainable: a route, cargo, tonnage and a date mean the visitor is close to booking. ≥ 70 is "hot".
 */
final class IntentScore
{
    public static function forLead(Lead $lead, ?Conversation $c = null): int
    {
        $score = 20;
        $score += filled($lead->origin) && filled($lead->destination) ? 20 : 0;
        $score += filled($lead->cargo) ? 15 : 0;
        $score += filled($lead->tonnage) ? 10 : 0;
        $score += $lead->preferred_date !== null ? 15 : 0;
        if ($lead->preferred_date !== null && $lead->preferred_date->lte(now()->addDays(7))) {
            $score += 10; // moving within a week
        }
        $score += $c !== null && filled($c->visitor_email) ? 5 : 0;
        $score += $c !== null && $c->turns >= 4 ? 5 : 0;

        return min(100, $score);
    }

    public static function summary(Lead $lead): string
    {
        $parts = array_filter([
            $lead->service_interest,
            filled($lead->origin) || filled($lead->destination) ? ($lead->origin ?? '?').' → '.($lead->destination ?? '?') : null,
            $lead->cargo,
            filled($lead->tonnage) ? $lead->tonnage.' t' : null,
            $lead->preferred_date?->format('j M Y'),
        ]);

        return 'Chat enquiry: '.(implode(', ', $parts) ?: 'details to confirm by phone').'.';
    }
}
