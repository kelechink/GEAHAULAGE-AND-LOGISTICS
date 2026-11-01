<?php

declare(strict_types=1);

namespace App\Domain\Crm;

use Illuminate\Support\Carbon;

/**
 * "Within 1 working day" (PRD 9.2): the same time on the next weekday, Lagos time.
 */
final class WorkingDays
{
    public static function next(Carbon $from): Carbon
    {
        $due = $from->copy()->setTimezone('Africa/Lagos')->addDay();
        while ($due->isWeekend()) {
            $due->addDay();
        }

        return $due;
    }
}
