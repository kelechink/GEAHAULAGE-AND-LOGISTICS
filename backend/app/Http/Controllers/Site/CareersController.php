<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Requests\CareerApplicationRequest;
use App\Models\CareerRole;
use App\Models\JobApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CareersController extends Controller
{
    public function show(): View
    {
        return view('site.careers', ['roles' => CareerRole::query()->open()->get()]);
    }

    public function store(CareerApplicationRequest $request): RedirectResponse
    {
        $data = $request->validated();

        JobApplication::query()->create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'role' => $data['role'],
            'years_experience' => $data['years'] ?? null,
            'licence_class' => $data['licence'] ?? null,
            'about' => $data['about'] ?? null,
        ]);

        return redirect()
            ->to(url()->previous(route('careers')).'#careers')
            ->with('applied', Str::of((string) $data['name'])->trim()->before(' ')->toString());
    }
}
