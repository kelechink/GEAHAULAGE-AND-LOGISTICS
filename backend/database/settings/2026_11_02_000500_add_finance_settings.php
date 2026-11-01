<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('company.bank_name', null);
        $this->migrator->add('company.bank_account_name', 'GEA Haulage and Logistics');
        $this->migrator->add('company.bank_account_number', null);
        $this->migrator->add('company.invoice_footer', 'Thank you for your business.');
        $this->migrator->add('company.einvoicing_required', false);
        $this->migrator->add('company.quote_valid_days', 14);
    }
};
