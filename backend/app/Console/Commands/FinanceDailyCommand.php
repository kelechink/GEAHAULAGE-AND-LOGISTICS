<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Finance\InvoiceService;
use App\Enums\InvoiceStatus;
use App\Mail\InvoiceMail;
use App\Models\Finance\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Daily: marks overdue invoices, then emails reminders 3 days before due, on the due date,
 * and every 7 days while overdue (PRD 10.3).
 */
class FinanceDailyCommand extends Command
{
    protected $signature = 'finance:daily';

    protected $description = 'Marks overdue invoices and sends payment reminders.';

    public function handle(InvoiceService $invoices): int
    {
        $overdue = $invoices->markOverdue();
        $today = now()->timezone('Africa/Lagos')->startOfDay();
        $sent = 0;

        $candidates = Invoice::query()->receivable()->whereNotNull('due_date')->with('customer')->get();
        foreach ($candidates as $inv) {
            if (blank($inv->customer->email) || $inv->balance_kobo <= 0) {
                continue;
            }
            $days = (int) $today->diffInDays($inv->due_date, false);
            $kind = match (true) {
                $days === 3 => 'due_soon',
                $days === 0 => 'due_today',
                // Weekly while overdue: 7, 14, 21… days after the due date.
                $days < 0 && $inv->status === InvoiceStatus::Overdue && (-$days) % 7 === 0 => 'overdue',
                default => null,
            };
            if ($kind === null || $inv->last_reminder_at?->isSameDay(now())) {
                continue;
            }
            Mail::to($inv->customer->email)->queue(new InvoiceMail($inv, $kind));
            $inv->forceFill(['last_reminder_at' => now()])->save();
            $sent++;
        }

        $this->info("{$overdue} invoices checked for overdue, {$sent} reminders sent.");

        return self::SUCCESS;
    }
}
