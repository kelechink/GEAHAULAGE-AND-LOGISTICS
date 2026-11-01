<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\ExpenseCategory;
use App\Models\Finance\Expense;
use App\Models\MaintenanceRecord;

/** Each maintenance cost posts to Expenses (PRD 10.2), and stays in step when edited. */
class MaintenanceRecordObserver
{
    public function saved(MaintenanceRecord $record): void
    {
        Expense::query()->updateOrCreate(
            ['maintenance_record_id' => $record->id],
            [
                'category' => ExpenseCategory::Maintenance,
                'spent_on' => $record->done_on,
                'amount_kobo' => $record->cost_kobo,
                'description' => $record->description,
                'vendor' => $record->getAttribute('workshop'),
                'truck_id' => $record->truck_id,
            ],
        );
    }
}
