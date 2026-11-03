<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Models\Service;
use App\Settings\CompanySettings;
use App\Settings\WebsiteSettings;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Shares company details, website copy and the service list with every public page.
 */
class SiteComposer
{
    /** @var Collection<int, Service>|null */
    private ?Collection $services = null;

    public function __construct(private readonly CompanySettings $company, private readonly WebsiteSettings $site) {}

    public function compose(View $view): void
    {
        $this->services ??= Service::query()->active()->get();

        $view->with([
            'company' => $this->company,
            'site' => $this->site,
            'services' => $this->services,
            'phoneDisplay' => $this->displayPhone($this->company->phone),
            'phoneTel' => '+'.$this->company->whatsapp,
        ]);
    }

    private function displayPhone(string $phone): string
    {
        $d = preg_replace('/\D+/', '', $phone) ?? $phone;

        return strlen($d) === 11 ? substr($d, 0, 4).' '.substr($d, 4, 3).' '.substr($d, 7) : $phone;
    }
}
