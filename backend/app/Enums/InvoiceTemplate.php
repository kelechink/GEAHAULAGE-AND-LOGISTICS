<?php

declare(strict_types=1);

namespace App\Enums;

/** The five approved document templates (PRD 10.3). */
enum InvoiceTemplate: string
{
    case CorporateClassic = 'corporate_classic';
    case BoldNavy = 'bold_navy';
    case HaulageTrip = 'haulage_trip';
    case EInvoice = 'e_invoice';
    case Proforma = 'proforma';

    public function label(): string
    {
        return match ($this) {
            self::CorporateClassic => 'Corporate Classic',
            self::BoldNavy => 'Bold Navy',
            self::HaulageTrip => 'Haulage Trip',
            self::EInvoice => 'E-Invoice',
            self::Proforma => 'Proforma / Quotation',
        };
    }

    /** @return array<string, string> */
    public static function invoiceOptions(): array
    {
        $o = [];
        foreach (self::cases() as $c) {
            if ($c !== self::Proforma) {
                $o[$c->value] = $c->label();
            }
        }

        return $o;
    }
}
