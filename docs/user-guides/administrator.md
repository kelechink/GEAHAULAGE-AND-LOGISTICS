# Administrator guide (FigureWeb)

Super Admin passes every permission check. Use it for setup and support, not daily work.

## Users and roles

**Settings → Users and roles.** One account per person. Roles: Management, Operations, Sales, Finance, Driver, Customer. Switch off **Active** when someone leaves; their history stays.

Everyone who uses `/admin` must set up two-factor sign-in with an authenticator app at their first sign-in (PRD 16). The **2FA** column shows who has it. If someone loses their phone and recovery codes, check it is really them (call them), then use **Reset two-factor**; they scan a new QR code at their next sign-in. Every setup, reset and recovery-code use is in the audit log. `TWO_FACTOR_ENFORCED` must stay `true` on staging and production. Create the first admin on a new server with `php artisan gea:make-admin "Name" email`.

## Settings

- **Company and tax:** company details, TIN, VAT, bank details on invoices, invoice footer, e-invoicing switch, quote validity.
- **Integrations:** tracker connectors (generic webhook, Traccar). Each has its own secret. See `docs/connector-integration.md`.
- **Import data:** CSV/Excel import of customers, trucks, drivers and routes. Templates are on the page. **Check file** first; nothing is saved until every row is valid. Re-importing updates matching records.
- **Audit log:** every change to shipments, invoices, payments, expenses and leads, with who and when.

Large imports can also run over SSH: `php artisan gea:import trucks trucks.xlsx` checks, `--commit` saves.

## Website and assistant content

**Content** holds the website copy, services, FAQs and careers. **Assistant → Knowledge** is what the chat may say; it is seeded from the website and must be kept in step when services change.

AI keys (`GEMINI_API_KEY`, `GROQ_API_KEY`) live only in `.env`. Without them the chat runs in "leave your details" mode. Daily usage shows on **Assistant → Conversations**.

## Monitoring and backups

See `docs/backups-and-monitoring.md`: nightly backups with an off-server copy, the quarterly restore test, UptimeRobot on `/` and `/health`, and error emails to `ALERT_EMAIL`.

## Driver app releases

The Android project is in `android-driver/`. GitHub Actions builds a debug APK on every change. For a release, add a signing key locally and run `./gradlew :app:assembleRelease -PapiBaseUrl=https://geahaulageandlogistics.com/api/`.
