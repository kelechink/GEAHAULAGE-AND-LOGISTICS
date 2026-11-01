<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Domain\Crm\LeadCaptureService;
use App\Http\Controllers\Controller;
use App\Http\Requests\QuoteRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class QuoteController extends Controller
{
    public function show(): View
    {
        return view('site.quote');
    }

    public function store(QuoteRequest $request, LeadCaptureService $leads): RedirectResponse
    {
        /** @var array{name: string, phone: string, email?: string|null, company?: string|null, service?: string|null, from?: string|null, to?: string|null, details?: string|null} $data */
        $data = $request->safe()->except('website');
        $leads->captureQuoteRequest($data);

        return redirect()
            ->to(url()->previous(route('quote')).'#quote')
            ->with('quote_sent', Str::of($data['name'])->trim()->before(' ')->toString());
    }
}
