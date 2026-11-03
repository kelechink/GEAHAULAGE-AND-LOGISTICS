<?php

declare(strict_types=1);

namespace App\Domain\Finance\EInvoice;

use App\Models\Finance\Invoice;

/**
 * Stand-in that returns realistic-looking but clearly fake values. Never use in production for FIRS.
 */
final class MockEInvoiceProvider implements EInvoiceProvider
{
    public function submit(Invoice $invoice): array
    {
        $irn = 'MOCK-'.strtoupper(substr(hash('sha256', $invoice->number.'|'.$invoice->total_kobo), 0, 20));

        return [
            'irn' => $irn,
            'qr_payload' => json_encode(['irn' => $irn, 'supplier_tin' => $invoice->supplier_tin, 'total' => $invoice->total_kobo, 'issued' => $invoice->issue_date?->toDateString()], JSON_UNESCAPED_SLASHES) ?: '',
            'stamp' => 'MOCK-STAMP '.now()->toIso8601String(),
            'status' => 'mock',
        ];
    }
}
