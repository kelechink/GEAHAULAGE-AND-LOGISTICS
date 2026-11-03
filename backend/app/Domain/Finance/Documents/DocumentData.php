<?php

declare(strict_types=1);

namespace App\Domain\Finance\Documents;

use App\Domain\Finance\AmountInWords;
use App\Enums\InvoiceTemplate;
use App\Models\Finance\DocumentLine;
use App\Models\Finance\Invoice;
use App\Models\Finance\Quote;
use App\Settings\CompanySettings;
use App\Support\Money;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Throwable;

/**
 * Everything a document template needs, already formatted, shared by the PDF and Word renderers
 * so both always show identical figures.
 */
final class DocumentData
{
    public function __construct(private readonly CompanySettings $company) {}

    /** @return array<string, mixed> */
    public function for(Invoice|Quote $doc): array
    {
        $doc->loadMissing(['customer', 'lines']);
        $isQuote = $doc instanceof Quote;
        $template = $isQuote ? InvoiceTemplate::Proforma : $doc->template;
        $fmt = fn (int $k): string => Money::kobo($k)->format();

        $data = [
            'template' => $template->value,
            'title' => $isQuote ? 'Proforma invoice / quotation' : ($template === InvoiceTemplate::EInvoice ? 'Tax invoice (e-invoice)' : 'Invoice'),
            'number' => $isQuote ? $doc->number : $doc->displayNumber(),
            'is_quote' => $isQuote,
            'is_draft' => ! $isQuote && $doc->number === null,
            'is_void' => ! $isQuote && $doc->status->value === 'void',
            'issue_date' => ($doc->issue_date ?? now())->format('j F Y'),
            'due_label' => $isQuote ? 'Valid until' : 'Due date',
            'due_date' => $isQuote ? $doc->valid_until->format('j F Y') : ($doc->due_date?->format('j F Y') ?? 'On issue'),
            'company' => [
                'name' => $this->company->company_name,
                'rc' => $this->company->rc_number,
                'address' => $this->company->address,
                'phone' => $this->company->phone,
                'email' => 'billing@geahaulageandlogistics.com',
                'tin' => $this->company->tin,
                'vat_number' => $this->company->vat_number,
                'logo' => public_path('assets/img/logo-blue.png'),
                'logo_white' => public_path('assets/img/logo-white.png'),
            ],
            'customer' => [
                'name' => $doc->customer->name,
                'address' => $doc->customer->billing_address,
                'email' => $doc->customer->email,
                'phone' => $doc->customer->phone,
                'tin' => $doc->customer->tin,
            ],
            'lines' => $doc->lines->map(fn (DocumentLine $l): array => [
                'description' => $l->description,
                'quantity' => rtrim(rtrim((string) $l->quantity, '0'), '.'),
                'unit_price' => $fmt($l->unit_price_kobo),
                'amount' => $fmt($l->amount_kobo),
            ])->all(),
            'subtotal' => $fmt($doc->subtotal_kobo),
            'discount' => $doc->discount_kobo > 0 ? $fmt($doc->discount_kobo) : null,
            'taxable' => $fmt($doc->subtotal_kobo - $doc->discount_kobo),
            'vat_rate' => rtrim(rtrim(number_format($doc->vat_rate_bp / 100, 2, '.', ''), '0'), '.').'%',
            'vat' => $fmt($doc->vat_kobo),
            'total' => $fmt($doc->total_kobo),
            'total_kobo' => $doc->total_kobo,
            'words' => AmountInWords::naira($doc->total_kobo),
            'paid' => ! $isQuote && $doc->amount_paid_kobo > 0 ? $fmt($doc->amount_paid_kobo) : null,
            'wht' => ! $isQuote && $doc->wht_kobo > 0 ? $fmt($doc->wht_kobo) : null,
            'credited' => ! $isQuote && $doc->credited_kobo > 0 ? $fmt($doc->credited_kobo) : null,
            'balance' => $isQuote ? null : $fmt($doc->balance_kobo),
            'bank' => array_filter([
                'Bank' => $this->company->bank_name,
                'Account name' => $this->company->bank_account_name,
                'Account number' => $this->company->bank_account_number,
            ]),
            'notes' => $doc->notes,
            'terms' => $isQuote ? ($doc->terms ?? 'Prices are valid until the date shown and depend on truck availability. VAT at the prevailing rate.') : null,
            'footer' => $this->company->invoice_footer,
            'trip' => null,
            'einvoice' => null,
        ];

        if (! $isQuote && $doc->shipment_id !== null) {
            $s = $doc->shipment()->with(['truck', 'driver'])->first();
            if ($s !== null) {
                $data['trip'] = array_filter([
                    'Tracking code' => $s->tracking_code,
                    'Waybill' => $s->waybill_number,
                    'Cargo' => $s->cargo_description.($s->tonnage ? ', '.rtrim(rtrim((string) $s->tonnage, '0'), '.').' t' : ''),
                    'From' => $s->origin,
                    'To' => $s->destination,
                    'Truck' => $s->truck?->registration,
                    'Driver' => $s->driver?->name,
                    'Pickup date' => $s->pickup_date?->format('j M Y'),
                    'Delivered' => $s->delivered_at?->timezone('Africa/Lagos')->format('j M Y'),
                ]);
            }
        }

        if (! $isQuote && ($template === InvoiceTemplate::EInvoice || filled($doc->irn))) {
            $data['einvoice'] = [
                'irn' => $doc->irn ?? 'Pending',
                'status' => $doc->einvoice_status,
                'stamp' => $doc->stamp,
                'supplier_tin' => $doc->supplier_tin ?? $this->company->tin,
                'buyer_tin' => $doc->buyer_tin ?? $doc->customer->tin,
                'qr' => filled($doc->qr_payload) ? $this->qr((string) $doc->qr_payload) : null,
            ];
        }

        return $data;
    }

    /** QR code as a PNG data URI (DomPDF renders data URIs). */
    private function qr(string $payload): ?string
    {
        try {
            $options = new QROptions(['outputType' => QRCode::OUTPUT_IMAGE_PNG, 'scale' => 4, 'outputBase64' => true]);

            return (new QRCode($options))->render($payload);
        } catch (Throwable) {
            return null;
        }
    }
}
