<div class="contact-grid">
    <a href="tel:{{ $phoneTel }}"><small>Call</small><b>{{ $phoneDisplay }}</b></a>
    <a href="https://wa.me/{{ $company->whatsapp }}" target="_blank" rel="noopener"><small>WhatsApp</small><b>{{ $phoneDisplay }}</b></a>
    <a href="mailto:{{ $site->email_info }}"><small>Email</small><b>{{ $site->email_info }}</b></a>
    <a href="https://www.google.com/maps/search/?api=1&amp;query={{ urlencode($company->address) }}" target="_blank" rel="noopener"><small>Office</small><b>{{ $company->address }}</b></a>
</div>
<div class="teams" style="display:flex;flex-direction:column;padding-top:8px">
    <span style="font-weight:600;font-size:17px;padding-bottom:10px">Reach the right team</span>
    <a href="mailto:{{ $site->email_sales }}"><small>Quotes and new business</small><b>{{ $site->email_sales }}</b></a>
    <a href="mailto:{{ $site->email_operations }}"><small>Bookings and operations</small><b>{{ $site->email_operations }}</b></a>
    <a href="mailto:{{ $site->email_support }}"><small>Tracking and delivery support</small><b>{{ $site->email_support }}</b></a>
    <a href="mailto:{{ $site->email_billing }}"><small>Invoices and payments</small><b>{{ $site->email_billing }}</b></a>
</div>
