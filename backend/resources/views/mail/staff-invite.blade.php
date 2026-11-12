<x-mail::message>
# Welcome to the team, {{ \Illuminate\Support\Str::of($user->name)->before(' ') }}

GEA Haulage and Logistics has set up your account on the **GEA Logistics Platform**, the system we use for shipments and tracking, customers, quotes and invoices.

<x-mail::panel>
**Your sign-in email:** {{ $user->email }}<br>
**Your role:** {{ $roles }}
</x-mail::panel>

<x-mail::button :url="$link" color="primary">Choose your password</x-mail::button>

**Getting started**

1. Click the button above and choose a password of at least 12 characters.
2. Sign in at {{ $loginUrl }}
3. Install an authenticator app on your phone (Google Authenticator or Microsoft Authenticator) and scan the QR code the platform shows you. Every sign-in after that asks for a 6-digit code from the app.

The link works for {{ $expiresHours }} hours. If it has expired, click "Forgot password?" on the sign-in page.

Keep your password and authenticator to yourself. GEA will never ask you for them by phone, email or WhatsApp.

Welcome aboard,<br>
GEA Haulage and Logistics
</x-mail::message>
