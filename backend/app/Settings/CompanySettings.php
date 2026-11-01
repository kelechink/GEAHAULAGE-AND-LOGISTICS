<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Company and tax details used on the website, documents and invoices (PRD 6, 10.3).
 */
class CompanySettings extends Settings
{
    public string $company_name;

    public string $rc_number;

    public string $address;

    public string $phone;

    public string $whatsapp;

    public string $email;

    public ?string $tin;

    public ?string $vat_number;

    /** Default VAT rate in percent, e.g. 7.5. */
    public float $vat_rate;

    /** Hides West Africa / cross-border copy on the website until GEA confirms it (PRD 7, Open Question 1). */
    public bool $show_cross_border;

    public ?string $bank_name;

    public ?string $bank_account_name;

    public ?string $bank_account_number;

    public ?string $invoice_footer;

    /** When on, an issued invoice without an IRN is flagged (PRD 10.3, FIRS readiness). */
    public bool $einvoicing_required;

    public int $quote_valid_days;

    public static function group(): string
    {
        return 'company';
    }
}
