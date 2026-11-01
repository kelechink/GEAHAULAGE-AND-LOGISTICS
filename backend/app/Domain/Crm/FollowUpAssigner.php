<?php

declare(strict_types=1);

namespace App\Domain\Crm;

use App\Enums\Role;
use App\Models\Crm\FollowUp;
use App\Models\User;

/**
 * Round-robin across active Sales users (PRD 9.2): the next user after whoever got the latest follow-up.
 */
final class FollowUpAssigner
{
    public function next(): ?User
    {
        $sales = User::query()
            ->role(Role::Sales->value)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($sales->isEmpty()) {
            return null;
        }

        $lastAssignee = FollowUp::query()->whereNotNull('assigned_to')->latest('id')->value('assigned_to');
        if ($lastAssignee === null) {
            return $sales->first();
        }

        return $sales->first(fn (User $u): bool => $u->id > $lastAssignee) ?? $sales->first();
    }
}
