# Go-live checklist

Work through this with GEA before switching the domain to the new platform (PRD 17, Phase 7). Tick each line and write who checked it.

## 1. Content and settings

- [ ] **Settings → Company and tax:** RC number, address, phone, WhatsApp, TIN, VAT number, VAT rate, bank details for invoices, invoice footer, quote validity
- [ ] **Content → Website content:** hero, about, mission, vision, values, steps, careers intro, mailboxes
- [ ] Logo files in `backend/public/assets/img/` checked by GEA (final versions)
- [ ] Services, FAQs and open career roles checked by GEA
- [ ] Cross-border coverage confirmed or left hidden (`show_cross_border`)
- [x] Privacy policy reviewed and approved by GEA's lawyer (3 Nov 2026)
- [ ] Terms of service reviewed by GEA's lawyer
- [x] Video and image licences confirmed for commercial use (3 Nov 2026)
- [ ] **Assistant → Knowledge:** office hours added to "Contact details and office hours"; every item read by GEA

## 2. Real data (Settings → Import data)

Download each template, fill it in, click **Check file**, fix any rows, then **Import**.

- [ ] Trucks (registration, type, capacity, document expiry dates)
- [ ] Drivers (phone numbers, licence, usual truck, smartphone yes/no). Import trucks first.
- [ ] Customers (name, phone, email, billing address, TIN, payment terms)
- [ ] Routes and checkpoints (towns GEA's drivers pass, with latitude and longitude)
- [ ] Demo data is **not** on the live database (`DatabaseSeeder` was never run there)

## 3. People and access

- [ ] One account per staff member with the right role (Management, Operations, Sales, Finance). No shared logins.
- [ ] Demo users absent; FigureWeb Super Admin created with `gea:make-admin`
- [ ] `TWO_FACTOR_ENFORCED=true`; every staff member has set up two-factor (Users and roles → **2FA** column) and stored their recovery codes
- [ ] Each driver with a smartphone has an app PIN (**Fleet → Drivers → Set app PIN**) and the APK installed
- [ ] Every role has had its training session using the guides in `docs/user-guides/`
- [ ] Key customers invited to the customer portal (Customers → **Invite to portal**) and sent `docs/user-guides/customers.md`
- [ ] Management knows how to answer data requests (Settings → **Data requests**) within 30 days

## 4. Email, AI and integrations

- [ ] Brevo: info@, sales@, support@, billing@ and logistic@ verified as senders, or the domain authenticated (until then `MAIL_PER_MAILBOX_SENDERS=false`)
- [ ] Mail sends from the GEA mailboxes (booking confirmation, quote received, new lead, invoice with download link, tracking updates, portal invitation). Check spam folders.
- [ ] Gemini and Groq keys set; a test chat answers; Assistant → Conversations shows usage
- [ ] Tracker connectors configured in **Settings → Integrations**, if GEA uses any
- [ ] FIRS e-invoicing: switched on only once GEA's credentials and compliance date are confirmed

## 5. Server

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, HTTPS forced
- [ ] Cron runs every minute (`/health` shows `scheduler: ok`)
- [ ] Nightly backup ran, off-server copy present (`php artisan backup:list`)
- [ ] **Restore tested on staging** (date, file, checked by): ____________________
- [ ] UptimeRobot monitors for `/` and `/health` alerting FigureWeb and GEA
- [ ] `ALERT_EMAIL` set; a test error reaches it
- [ ] Lighthouse on the home page: performance 90+, accessibility 100

## 6. Pilot (two weeks)

- [ ] 3 to 5 trucks run every trip through the platform: booking, assignment, driver app or console updates, delivery proof, invoice
- [ ] Customers on those trips get tracking links and confirm the page works on their phones
- [ ] Daily digest arrives at 7am for Management and Operations
- [ ] Bugs logged with FigureWeb; no P1 (blocking) bugs open at the end of the pilot

## 7. Switch over

- [ ] DNS for the domain points at the Laravel app's `public/` folder
- [ ] Old static site files removed from the document root
- [ ] Google Search Console: sitemap `https://geahaulageandlogistics.com/sitemap.xml` submitted
- [ ] GEA signs off
