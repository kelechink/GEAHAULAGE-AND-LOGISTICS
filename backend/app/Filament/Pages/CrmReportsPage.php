<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Crm\CrmReports;
use App\Enums\Permission;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class CrmReportsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'CRM reports';

    protected static ?string $title = 'CRM reports';

    protected static ?string $slug = 'crm-reports';

    protected static ?int $navigationSort = 90;

    protected static string $view = 'filament.pages.crm-reports';

    public string $period = '30';

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::CrmView->value) ?? false;
    }

    /** @return array<string, mixed> */
    public function getReport(): array
    {
        $to = now();
        $from = match ($this->period) {
            '7' => now()->subDays(7),
            '90' => now()->subDays(90),
            '365' => now()->subYear(),
            default => now()->subDays(30),
        };
        $r = app(CrmReports::class);

        return [
            'bySource' => $r->bySource($from, $to),
            'funnel' => $r->funnel($from, $to),
            'response' => $r->responseTime($from, $to),
            'wonByMonth' => $r->wonByMonth(),
            'from' => Carbon::parse($from)->format('j M Y'),
        ];
    }
}
