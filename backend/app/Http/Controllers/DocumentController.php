<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Documents\DocumentLinks;
use App\Domain\Finance\Documents\DocumentRenderer;
use App\Enums\InvoiceStatus;
use App\Models\Finance\Invoice;
use App\Models\ProofOfDelivery;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Serves documents behind signed, expiring links (the `signed` middleware rejects anything else). */
class DocumentController extends Controller
{
    public function invoice(Invoice $invoice, DocumentRenderer $renderer): Response
    {
        abort_if($invoice->status === InvoiceStatus::Draft, 404);

        return response($renderer->pdf($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$renderer->filename($invoice, 'pdf').'"',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    public function proof(ProofOfDelivery $proof, string $kind): StreamedResponse
    {
        $path = (string) $proof->getAttribute(DocumentLinks::KINDS[$kind] ?? 'photo_path');
        abort_if($path === '' || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex']);
    }
}
