@php
    $t = $d['template'];
    $navy = '#172F66'; $gold = '#E8A33D'; $ink = '#1C2333'; $muted = '#5E6578'; $line = '#D9DCE3';
    $accent = match ($t) { 'bold_navy' => $gold, 'e_invoice' => '#1E3A8A', 'proforma' => '#1E3A8A', default => $navy };
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $d['title'] }} {{ $d['number'] }}</title>
<style>
    @page { margin: 28px 34px 60px; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 10.5px; color: {{ $ink }}; line-height: 1.45; }
    table { width: 100%; border-collapse: collapse; }
    .muted { color: {{ $muted }}; }
    .right { text-align: right; }
    h1 { margin: 0; font-size: 22px; letter-spacing: .5px; text-transform: uppercase; color: {{ $t === 'bold_navy' ? '#fff' : $navy }}; }
    .band { background: {{ $navy }}; color: #fff; padding: 18px 20px; margin: -28px -34px 18px; }
    .band .muted { color: #C9CEDA; }
    .rule { border-top: 3px solid {{ $accent }}; margin: 10px 0 14px; }
    .meta td { padding: 2px 0; vertical-align: top; }
    .box { border: 1px solid {{ $line }}; padding: 10px 12px; }
    .label { font-size: 8.5px; text-transform: uppercase; letter-spacing: .8px; color: {{ $muted }}; margin-bottom: 3px; }
    .lines th { background: {{ $t === 'corporate_classic' ? '#F1F3F7' : $navy }}; color: {{ $t === 'corporate_classic' ? $ink : '#fff' }}; text-align: left; padding: 7px 8px; font-size: 9.5px; text-transform: uppercase; letter-spacing: .5px; }
    .lines td { padding: 7px 8px; border-bottom: 1px solid {{ $line }}; vertical-align: top; }
    .totals td { padding: 4px 8px; }
    .totals .grand td { font-size: 13px; font-weight: bold; border-top: 2px solid {{ $accent }}; padding-top: 7px; }
    .totals .balance td { font-weight: bold; color: {{ $navy }}; }
    .words { margin-top: 8px; font-style: italic; }
    .trip td { padding: 3px 8px 3px 0; }
    .stamp { position: absolute; top: 230px; left: 160px; font-size: 64px; color: rgba(200,16,46,.18); transform: rotate(-24deg); font-weight: bold; letter-spacing: 6px; }
    .footer { position: fixed; bottom: -40px; left: 0; right: 0; font-size: 8.5px; color: {{ $muted }}; border-top: 1px solid {{ $line }}; padding-top: 6px; }
    .gold { color: {{ $gold }}; }
</style>
</head>
<body>
@if ($d['is_void'])<div class="stamp">VOID</div>@elseif ($d['is_draft'])<div class="stamp" style="color:rgba(94,101,120,.18)">DRAFT</div>@endif

@if ($t === 'bold_navy')
    <div class="band">
        <table><tr>
            <td style="width:55%">@if (is_file($d['company']['logo_white']))<img src="{{ $d['company']['logo_white'] }}" style="height:54px">@endif
                <div class="muted" style="margin-top:6px">{{ $d['company']['address'] }}</div></td>
            <td class="right"><h1>{{ $d['title'] }}</h1><div style="font-size:13px;margin-top:4px" class="gold">{{ $d['number'] }}</div></td>
        </tr></table>
    </div>
@else
    <table><tr>
        <td style="width:55%">@if (is_file($d['company']['logo']))<img src="{{ $d['company']['logo'] }}" style="height:58px">@endif</td>
        <td class="right"><h1>{{ $d['title'] }}</h1><div style="font-size:13px;margin-top:4px;color:{{ $accent }}">{{ $d['number'] }}</div></td>
    </tr></table>
    <div class="rule"></div>
@endif

<table class="meta" style="margin-bottom:14px"><tr>
    <td style="width:50%;padding-right:12px">
        <div class="label">From</div>
        <strong>{{ $d['company']['name'] }}</strong><br>
        @if ($t !== 'bold_navy'){{ $d['company']['address'] }}<br>@endif
        RC {{ $d['company']['rc'] }} · {{ $d['company']['phone'] }} · {{ $d['company']['email'] }}<br>
        @if ($d['company']['tin'])TIN {{ $d['company']['tin'] }}@endif @if ($d['company']['vat_number']) · VAT {{ $d['company']['vat_number'] }}@endif
    </td>
    <td style="width:50%">
        <div class="label">Bill to</div>
        <strong>{{ $d['customer']['name'] }}</strong><br>
        @if ($d['customer']['address']){{ $d['customer']['address'] }}<br>@endif
        {{ collect([$d['customer']['phone'], $d['customer']['email']])->filter()->implode(' · ') }}<br>
        @if ($d['customer']['tin'])TIN {{ $d['customer']['tin'] }}@endif
    </td>
</tr></table>

<table class="meta" style="margin-bottom:14px"><tr>
    <td><div class="label">Issue date</div>{{ $d['issue_date'] }}</td>
    <td><div class="label">{{ $d['due_label'] }}</div>{{ $d['due_date'] }}</td>
    @if ($d['balance'])<td class="right"><div class="label">Balance due</div><strong style="font-size:13px;color:{{ $navy }}">{{ $d['balance'] }}</strong></td>@endif
</tr></table>

@if ($d['trip'] && in_array($t, ['haulage_trip', 'bold_navy', 'e_invoice'], true))
    <div class="box" style="margin-bottom:14px">
        <div class="label">Trip details</div>
        <table class="trip"><tr>
            @foreach ($d['trip'] as $k => $v)
                <td style="width:33%"><span class="muted">{{ $k }}:</span> {{ $v }}</td>
                @if ($loop->iteration % 3 === 0 && ! $loop->last)</tr><tr>@endif
            @endforeach
        </tr></table>
    </div>
@endif

<table class="lines">
    <thead><tr><th>Description</th><th class="right" style="width:9%">Qty</th><th class="right" style="width:18%">Unit price</th><th class="right" style="width:18%">Amount</th></tr></thead>
    <tbody>
    @foreach ($d['lines'] as $l)
        <tr><td>{{ $l['description'] }}</td><td class="right">{{ $l['quantity'] }}</td><td class="right">{{ $l['unit_price'] }}</td><td class="right">{{ $l['amount'] }}</td></tr>
    @endforeach
    </tbody>
</table>

<table style="margin-top:6px"><tr>
    <td style="width:52%;vertical-align:top;padding-right:16px">
        <div class="words"><span class="muted">Amount in words:</span> {{ $d['words'] }}</div>
        @if ($d['bank'])
            <div class="box" style="margin-top:12px"><div class="label">Pay by bank transfer</div>
                @foreach ($d['bank'] as $k => $v)<div><span class="muted">{{ $k }}:</span> <strong>{{ $v }}</strong></div>@endforeach
                @if (! $d['is_quote'])<div class="muted" style="margin-top:4px">Use {{ $d['number'] }} as the payment reference.</div>@endif
            </div>
        @endif
        @if ($d['notes'])<div style="margin-top:10px"><div class="label">Notes</div>{{ $d['notes'] }}</div>@endif
        @if ($d['terms'])<div style="margin-top:10px"><div class="label">Terms</div>{{ $d['terms'] }}</div>@endif
    </td>
    <td style="width:48%;vertical-align:top">
        <table class="totals">
            <tr><td>Subtotal</td><td class="right">{{ $d['subtotal'] }}</td></tr>
            @if ($d['discount'])<tr><td>Discount</td><td class="right">-{{ $d['discount'] }}</td></tr><tr><td>Taxable amount</td><td class="right">{{ $d['taxable'] }}</td></tr>@endif
            <tr><td>VAT ({{ $d['vat_rate'] }})</td><td class="right">{{ $d['vat'] }}</td></tr>
            <tr class="grand"><td>Total</td><td class="right">{{ $d['total'] }}</td></tr>
            @if ($d['paid'])<tr><td>Paid</td><td class="right">-{{ $d['paid'] }}</td></tr>@endif
            @if ($d['wht'])<tr><td>WHT deducted</td><td class="right">-{{ $d['wht'] }}</td></tr>@endif
            @if ($d['credited'])<tr><td>Credit notes</td><td class="right">-{{ $d['credited'] }}</td></tr>@endif
            @if ($d['balance'])<tr class="balance"><td>Balance due</td><td class="right">{{ $d['balance'] }}</td></tr>@endif
        </table>
    </td>
</tr></table>

@if ($d['einvoice'])
    <div class="box" style="margin-top:16px">
        <table><tr>
            <td style="vertical-align:top">
                <div class="label">FIRS e-invoice</div>
                <div><span class="muted">IRN:</span> <strong>{{ $d['einvoice']['irn'] }}</strong></div>
                <div><span class="muted">Supplier TIN:</span> {{ $d['einvoice']['supplier_tin'] ?? 'Not set' }} · <span class="muted">Buyer TIN:</span> {{ $d['einvoice']['buyer_tin'] ?? 'Not given' }}</div>
                @if ($d['einvoice']['stamp'])<div class="muted">{{ $d['einvoice']['stamp'] }}</div>@endif
                @if ($d['einvoice']['status'] === 'mock')<div style="color:#C8102E;margin-top:4px">Test IRN from the mock adapter. Not valid for FIRS.</div>@endif
            </td>
            @if ($d['einvoice']['qr'])<td class="right" style="width:110px"><img src="{{ $d['einvoice']['qr'] }}" style="width:100px;height:100px"></td>@endif
        </tr></table>
    </div>
@endif

<div class="footer">
    {{ $d['footer'] }} {{ $d['company']['name'] }} · RC {{ $d['company']['rc'] }} · {{ $d['company']['address'] }}
</div>
</body>
</html>
