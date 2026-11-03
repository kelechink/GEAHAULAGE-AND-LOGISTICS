<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Domain\Documents\DocumentLinks;
use App\Domain\Finance\Documents\DocumentRenderer;
use App\Domain\Tracking\EtaEstimator;
use App\Enums\InvoiceStatus;
use App\Enums\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Finance\Invoice;
use App\Models\Finance\Quote;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Customer portal pages. Every query is scoped to the signed-in user's customer account. */
class PortalController extends Controller
{
    public function home(Request $request, EtaEstimator $eta): View
    {
        $customer = $this->customer($request);
        $shipments = $customer->shipments()->with(['proofs', 'latestPosition'])->latest('id')->limit(30)->get();
        $invoices = $customer->invoices()->whereNot('status', InvoiceStatus::Draft->value)->latest('issue_date')->limit(30)->get();
        $quotes = $customer->quotes()->whereIn('status', [QuoteStatus::Sent->value, QuoteStatus::Accepted->value])->latest('issue_date')->limit(10)->get();
        $owed = (int) $customer->invoices()->receivable()->sum('balance_kobo');

        return view('site.portal.home', [
            'customer' => $customer,
            'active' => $shipments->filter(fn ($s): bool => $s->stage->isActive())->values(),
            'past' => $shipments->reject(fn ($s): bool => $s->stage->isActive())->values(),
            'invoices' => $invoices,
            'quotes' => $quotes,
            'owed' => Money::kobo($owed)->format(),
            'eta' => fn ($s): ?string => EtaEstimator::describe($eta->estimate($s)),
            'proofLinks' => fn ($s): array => ($p = $s->proofs->sortByDesc('id')->first()) !== null ? DocumentLinks::proofLinks($p, 60) : [],
            'money' => fn (int $kobo): string => Money::kobo($kobo)->format(),
        ]);
    }

    public function invoice(Request $request, Invoice $invoice, DocumentRenderer $renderer): Response
    {
        abort_unless($invoice->customer_id === $this->customer($request)->id && $invoice->status !== InvoiceStatus::Draft, 404);

        return $this->pdf($renderer->pdf($invoice), $renderer->filename($invoice, 'pdf'));
    }

    public function quote(Request $request, Quote $quote, DocumentRenderer $renderer): Response
    {
        abort_unless($quote->customer_id === $this->customer($request)->id && $quote->status !== QuoteStatus::Draft, 404);

        return $this->pdf($renderer->pdf($quote), $renderer->filename($quote, 'pdf'));
    }

    private function pdf(string $bytes, string $name): Response
    {
        return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$name.'"', 'Cache-Control' => 'private, no-store']);
    }

    private function customer(Request $request): Customer
    {
        /** @var User $user */
        $user = $request->user();

        return Customer::query()->findOrFail($user->customer_id);
    }
}
