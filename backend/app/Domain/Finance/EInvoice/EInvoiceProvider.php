<?php

declare(strict_types=1);

namespace App\Domain\Finance\EInvoice;

use App\Models\Finance\Invoice;

/**
 * FIRS e-invoicing (PRD 10.3). The real FIRS or access-point adapter is added once GEA confirms its
 * compliance phase and credentials; until then the mock adapter is bound.
 */
interface EInvoiceProvider
{
    /** @return array{irn: string, qr_payload: string, stamp: string, status: string} */
    public function submit(Invoice $invoice): array;
}
