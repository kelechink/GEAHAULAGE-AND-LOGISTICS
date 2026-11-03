<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\CareerRole;
use App\Models\Faq;
use App\Models\Service;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(): View
    {
        return view('site.home', [
            'faqs' => Faq::query()->active()->get(),
            'roles' => CareerRole::query()->open()->get(),
        ]);
    }

    public function service(Service $service): View
    {
        abort_unless($service->is_active, 404);

        return view('site.service', [
            'service' => $service,
            'others' => Service::query()->active()->whereKeyNot($service->id)->get(),
        ]);
    }

    public function privacy(): View
    {
        return view('site.legal.privacy');
    }

    public function terms(): View
    {
        return view('site.legal.terms');
    }

    public function sitemap(): Response
    {
        $urls = collect([route('home'), route('quote'), route('careers'), route('track'), route('privacy'), route('terms')])
            ->merge(Service::query()->active()->get()->map(fn (Service $s): string => route('services.show', $s)));

        return response()->view('site.sitemap', ['urls' => $urls], 200)->header('Content-Type', 'application/xml');
    }
}
