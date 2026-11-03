<!doctype html>
<html><head><meta charset="utf-8"><title>{{ $title }}</title>
<style>
 body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1C2333; }
 h1 { font-size: 16px; color: #172F66; margin: 0 0 2px; } .muted { color: #5E6578; }
 table { width: 100%; border-collapse: collapse; margin-top: 12px; }
 th { background: #172F66; color: #fff; text-align: left; padding: 6px; } td { padding: 5px 6px; border-bottom: 1px solid #D9DCE3; }
</style></head>
<body>
<div class="muted">GEA Haulage and Logistics</div>
<h1>{{ $title }}</h1><div class="muted">{{ $period }} · Generated {{ now()->timezone('Africa/Lagos')->format('j M Y, g:ia') }}</div>
<table><thead><tr>@foreach ($headings as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
<tbody>@forelse ($rows as $r)<tr>@foreach ($r as $v)<td>{{ $v }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($headings) }}">No data in this period.</td></tr>@endforelse</tbody></table>
</body></html>
