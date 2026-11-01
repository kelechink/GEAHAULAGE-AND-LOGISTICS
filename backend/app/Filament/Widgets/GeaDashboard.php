<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Reporting\DashboardData;
use Filament\Widgets\Widget;

/**
 * The admin dashboard: KPI tiles, "what's going on" summary, alerts and four charts.
 * Each block only appears for roles allowed to see its data.
 */
class GeaDashboard extends Widget
{
    protected static string $view = 'filament.widgets.gea-dashboard';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -10;

    /** Render with the page: scripts in lazily-loaded widget HTML would not run. */
    protected static bool $isLazy = false;

    /** @return array<string, mixed> */
    public function getData(): array
    {
        $user = auth()->user();

        return $user !== null ? app(DashboardData::class)->for($user) : ['kpis' => [], 'summary' => [], 'alerts' => [], 'charts' => []];
    }
}
