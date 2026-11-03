<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('company.company_name', 'GEA Haulage and Logistics');
        $this->migrator->add('company.rc_number', '9198613');
        $this->migrator->add('company.address', 'Plot 6, Block 48, Olueasanya Street, off AIT Road, Kola Alagbado, Ifako-Ijaye, Lagos');
        $this->migrator->add('company.phone', '08162163235');
        $this->migrator->add('company.whatsapp', '2348162163235');
        $this->migrator->add('company.email', 'gaeglobal1@gmail.com');
        $this->migrator->add('company.tin', null);
        $this->migrator->add('company.vat_number', null);
        $this->migrator->add('company.vat_rate', 7.5);
        $this->migrator->add('company.show_cross_border', false);
    }
};
