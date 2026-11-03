<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/** After staff sign in, go back only to a back-office page they asked for; never to a portal or website page. */
class StaffLoginResponse implements LoginResponse
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $home = Filament::getUrl() ?? url('/admin');
        $intended = session()->pull('url.intended');
        $target = is_string($intended) && str_starts_with($intended, url('/admin')) ? $intended : $home;

        return redirect()->to($target);
    }
}
