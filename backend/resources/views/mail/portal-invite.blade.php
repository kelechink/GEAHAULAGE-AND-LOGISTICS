<x-mail::message>
Hello {{ $user->name }},

GEA Haulage and Logistics has set up a customer portal account for **{{ $company }}**. In the portal you can follow your shipments, see proof of delivery, and download your invoices and quotes at any time.

<x-mail::button :url="$link">Choose your password</x-mail::button>

This link works for 60 minutes. If it has expired, use "Forgot password" on the sign-in page.

Sign in later at {{ route('portal.login') }} with **{{ $user->email }}**.

GEA Haulage and Logistics
</x-mail::message>
