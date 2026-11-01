<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Tracking search. Live shipment tracking arrives in Phase 3; until then every code is "not found".
 */
class TrackController extends Controller
{
    public function search(Request $request): View
    {
        $code = Str::of((string) $request->query('code', ''))->upper()->replaceMatches('/\s+/', '')->limit(20, '')->toString();

        return view('site.track', ['code' => $code]);
    }
}
