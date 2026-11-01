<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Domain\Tracking\PublicTracking;
use App\Domain\Tracking\TrackingCode;
use App\Http\Controllers\Controller;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Throwable;

class TrackController extends Controller
{
    public function search(Request $request): View|RedirectResponse
    {
        $code = TrackingCode::normalise(mb_substr((string) $request->query('code', ''), 0, 24));
        if ($code !== '' && Shipment::query()->where('tracking_code', $code)->exists()) {
            return redirect()->route('track.show', $code);
        }

        return view('site.track', ['code' => $code]);
    }

    public function show(string $code, PublicTracking $tracking): View
    {
        $shipment = Shipment::query()->where('tracking_code', TrackingCode::normalise($code))->firstOrFail();

        return view('site.track-show', ['data' => $tracking->snapshot($shipment)]);
    }

    /** Polled every 15 seconds by the tracking page (PRD 8.5). */
    public function updates(Request $request, string $code, PublicTracking $tracking): JsonResponse
    {
        $shipment = Shipment::query()->where('tracking_code', TrackingCode::normalise($code))->firstOrFail();

        try {
            $since = Carbon::parse((string) $request->query('since', ''));
        } catch (Throwable) {
            $since = null;
        }

        $payload = $since !== null && $request->filled('since') ? $tracking->since($shipment, $since) : $tracking->snapshot($shipment);

        return response()->json($payload)->header('Cache-Control', 'no-store');
    }

    public function subscribe(Request $request, string $code): RedirectResponse
    {
        $shipment = Shipment::query()->where('tracking_code', TrackingCode::normalise($code))->firstOrFail();
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:160'],
            'website' => ['prohibited'],
        ], ['email.required' => 'Enter the email address for updates.', 'email.email' => 'Enter an email address like name@company.com.']);

        $shipment->subscriptions()->firstOrCreate(['email' => mb_strtolower((string) $data['email'])]);

        return redirect()->route('track.show', $shipment->tracking_code)->with('subscribed', true);
    }
}
