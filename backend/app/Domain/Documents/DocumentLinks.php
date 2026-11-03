<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Models\Finance\Invoice;
use App\Models\ProofOfDelivery;
use Illuminate\Support\Facades\URL;

/**
 * Signed, expiring links to documents (PRD 16: "Signed URLs for documents"). Anyone with the link can open
 * that one file until it expires; changing the URL breaks the signature. Files never sit in a public folder.
 */
final class DocumentLinks
{
    public const INVOICE_DAYS = 30;

    public const PROOF_DAYS = 14;

    public const KINDS = ['photo' => 'photo_path', 'signature' => 'signature_path', 'document' => 'document_path'];

    public static function invoice(Invoice $invoice, int $days = self::INVOICE_DAYS): string
    {
        return URL::temporarySignedRoute('documents.invoice', now()->addDays($days), ['invoice' => $invoice->id]);
    }

    public static function proof(ProofOfDelivery $proof, string $kind, ?int $minutes = null): ?string
    {
        $column = self::KINDS[$kind] ?? null;
        if ($column === null || blank($proof->getAttribute($column))) {
            return null;
        }

        return URL::temporarySignedRoute('documents.proof', $minutes !== null ? now()->addMinutes($minutes) : now()->addDays(self::PROOF_DAYS),
            ['proof' => $proof->id, 'kind' => $kind]);
    }

    /** @return array<string, string> label => signed URL, for every file the proof has */
    public static function proofLinks(ProofOfDelivery $proof, ?int $minutes = null): array
    {
        $out = [];
        foreach (['photo' => 'Photo', 'signature' => 'Signature', 'document' => 'Signed waybill'] as $kind => $label) {
            $url = self::proof($proof, $kind, $minutes);
            if ($url !== null) {
                $out[$label] = $url;
            }
        }

        return $out;
    }
}
