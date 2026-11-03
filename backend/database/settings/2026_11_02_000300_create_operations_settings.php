<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('operations.average_speed_kmh', 45);
        $this->migrator->add('operations.stale_after_minutes', 60);
    }
};
