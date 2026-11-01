# PRD — GEA Logistics Platform (v2: Laravel edition)

**Product:** GEA Logistics Platform: corporate website, CRM, logistics management system and AI assistant
**Client:** GEA Haulage and Logistics (RC 9198613), Plot 6, Block 48, Olueasanya Street, off AIT Road, Kola Alagbado, Ifako-Ijaye, Lagos
**Client contact:** Godwin Atueyi, 08162163235
**Built by:** FigureWeb, a Figuraid Ltd. brand
**Version:** 2.0, 1 November 2026. This replaces v1, which used a JavaScript stack.

**Source documents:**
- GEA Logistics Management System Proposal
- Proforma Invoices FW-PRO-2026-1029-01 (website, ₦680,000) and FW-PRO-2026-1030-02 (management system, ₦3,500,000)
- GEA Website Onboarding Questionnaire
- GEA Company Profile
- GEA invoice templates 1–5
- Website design draft "GEA Website v3" (Claude Design export)

> **How to use this file with Claude Code:** put it at the repo root as `PRD.md`, together with the unzipped design draft in `/design-draft`. Section 18 sets out the build order, with a ready-to-paste prompt for each phase. Build one phase at a time and check it against that phase's acceptance criteria before moving on.

---

## 0. What changed from v1

| Area | v1 | v2 (this document) |
|---|---|---|
| Backend | Node / Next.js | **PHP 8.2+ / Laravel 11** with MySQL 8 |
| Admin UI | React + shadcn | **Filament 3 (Livewire)**, server-rendered PHP |
| Hosting | VPS with Docker | **FigureWeb's own cPanel hosting** (ResellerClub), near-zero extra cost |
| Realtime | WebSockets | **Short polling (15s)**, which works on shared hosting |
| Driver app | React Native | **Native Android (Kotlin)** with background GPS |
| GPS source | Traccar + phone | **Hybrid:** driver phone GPS **plus** manual updates by staff, with a **connector slot** for future tracker companies |
| Maps | Google Maps | **Leaflet + OpenStreetMap** (free). Google Maps is optional later |
| AI | Anthropic Claude API | **Free tier: Google Gemini API**, with **Groq** as a free fallback |
| AI scope | Customer chat only | **Proactive website assistant + CRM lead capture + operations assistant** |
| CRM | Not in scope | **Built in:** leads, contacts, pipeline, follow-ups |
| Website | To be built | **Port the existing "GEA Website v3" design** into Laravel Blade, with fixes |
| Documents | PDF only | **PDF and Word (.docx) export** for invoices, quotes and reports |

JavaScript is not used for the application. All business logic, data, auth and the admin run in PHP. The only JavaScript is what a browser needs for the website's motion effects, the map and the chat bubble, loaded as small, separate scripts.

## 1. Summary

GEA is a Lagos haulage company founded in 2020. It runs 35 trucks of 5–30 tonnes. We will build one platform under one brand and one login:

1. **The marketing website** (geahaulageandlogistics.com), based on the approved v3 design, with motion.
2. **Hybrid shipment tracking.** The driver's phone sends GPS, staff update stages manually when needed, and customers see every update on the tracking page and the route line within seconds.
3. **CRM.** Every enquiry from the website, the AI chat, WhatsApp, phone or walk-in becomes a lead. Leads move through a pipeline with follow-up reminders.
4. **Operations:** shipments and dispatch, fleet, drivers, staff and roles.
5. **Finance:** invoicing (VAT, discount, WHT, balance, FIRS e-invoicing readiness), payments, expenses and reports, with PDF and Word export.
6. **Android driver app** (Kotlin) for GPS, trip stages and proof of delivery.
7. **AI assistant (free API).** It engages website visitors proactively, answers from GEA's own content, tracks shipments, captures leads into the CRM, and gives staff a daily operations digest and alerts.
8. **Management dashboard** with one screen for trips, revenue, receivables, fleet, leads and alerts.

**Budget constraint:** this project is priced close to cost. Every choice below favours free or already-owned infrastructure: FigureWeb cPanel hosting, OpenStreetMap, free AI tiers, and email over paid SMS or WhatsApp.

## 2. Goals and success metrics (3 months after go-live)

| Goal | Metric | Target |
|---|---|---|
| Self-service tracking | Status checks done online vs. by phone call | 60%+ online |
| Live visibility | Active trips with an update (GPS or manual) in the last 60 minutes | 90%+ |
| Lead capture | Enquiries logged in the CRM | 100% of web and AI enquiries automatically, plus phone and walk-in logged by staff |
| Lead follow-up | Leads contacted within 1 working day | 90%+ |
| Faster billing | Delivery → invoice issued | Under 24 hours |
| Website speed | Lighthouse mobile performance | ≥ 85; LCP < 2.5s on 4G |
| Running cost | Extra monthly third-party spend | ₦0 at launch (AI and maps on free tiers) |

## 3. Non-goals (v1)

- iPhone driver app (Android only).
- GPS hardware supply. The connector slot (Section 8.4) is ready for when GEA buys trackers or works with a tracker company.
- Payroll, HR, inventory.
- Card payment collection. Bank transfer only at launch, with Paystack possible later.
- Paid SMS and WhatsApp Business API at launch. These are optional add-ons, and the code has the hooks for them (Section 11).
- Cross-border operations. **Unconfirmed; see Open Questions.**

## 4. Users and roles

| Role | Access |
|---|---|
| **Super Admin** (FigureWeb) | Everything: settings, integrations, AI settings, audit log |
| **Management / MD** | Read everything, all dashboards and reports. Approves voids and large discounts |
| **Operations** | Shipments, dispatch, tracking, manual stage/location updates, fleet, drivers |
| **Sales / Customer Service** | CRM (leads, contacts, follow-ups), quotes, AI chat inbox and handoffs |
| **Finance / Accounts** | Invoices, payments, expenses, reports. Read-only on shipments |
| **Driver** | Android app only: own trips, GPS, stages, proof of delivery |
| **Customer** (optional login) | Own shipments and invoices. Public tracking needs no login |

RBAC is enforced server-side with Laravel policies and `spatie/laravel-permission`. Every change to shipments, invoices, payments, expenses and leads is written to the audit log (`spatie/laravel-activitylog`).

## 5. Tech stack

| Layer | Choice | Notes |
|---|---|---|
| Language / framework | **PHP 8.2+, Laravel 12** (upgraded from 11 for security fixes) | Runs on cPanel |
| Database | **MySQL 8** (or MariaDB 10.6+) | Available on FigureWeb cPanel |
| Admin panel | **Filament 3** (Livewire) | Fast CRUD, tables, filters, dashboards and charts, all PHP |
| Public site | **Blade templates** ported from the v3 design | Plain HTML/CSS, plus small JS files for motion |
| Website motion | GSAP + ScrollTrigger (CDN), CSS animations | Browser-only. See Section 14 |
| Maps | **Leaflet + OpenStreetMap tiles** | Free. Respect the OSM tile usage policy; switch to a free MapTiler key if traffic grows |
| Geocoding / ETA | Nominatim (light use, cached) + an ETA estimate from distance and average speed | A Google Distance Matrix adapter can be added later |
| Auth | Laravel Breeze/Fortify (web), **Sanctum** API tokens (driver app) | 2FA for admin roles (Filament / Fortify) |
| Queue / scheduler | `database` queue driver + cPanel **cron every minute**: `php artisan schedule:run` | No Redis needed. Schedule `queue:work --stop-when-empty` |
| Realtime | **Polling** `GET /api/track/{code}/updates?since=` every 15s | Works on shared hosting. Upgrade path: Pusher or Laravel Reverb on a VPS |
| PDF | `barryvdh/laravel-dompdf` | Blade versions of the 5 invoice templates |
| Word | `phpoffice/phpword` | .docx export of invoices, quotes and reports (Section 10.4) |
| Email | cPanel SMTP using the GEA mailboxes (billing@, support@, sales@, info@) | Free |
| AI | **Gemini API (free tier)** primary, **Groq (free tier)** fallback | Behind an `AiProvider` interface (Section 12) |
| Driver app | **Kotlin**, Jetpack Compose, Fused Location Provider, Room (offline), WorkManager, Retrofit | Foreground service for background GPS |
| Files | Laravel `public`/`local` disks on the cPanel account | Proof-of-delivery photos, receipts, PDFs. Daily backup |
| Monitoring | UptimeRobot (free) + Laravel log alerts by email | |

**cPanel requirements to confirm on the FigureWeb server:**
- PHP 8.2+ with `pdo_mysql`, `mbstring`, `gd`, `zip`, `intl`, `bcmath` and `fileinfo`
- Composer, or deploying the `vendor/` folder
- SSH access (preferred) and cron
- SSL (AutoSSL)
- The document root pointed at `public/`

## 6. Information architecture

```
geahaulageandlogistics.com
├── /                    Home (v3 design: video hero, tracking bar, services, fleet, coverage, values, process, careers, FAQ, quote)
├── /services/{slug}     8 service pages (from Service.dc.html)
├── /track               Tracking search  →  /track/{code}  live tracking page
├── /quote               Quote request (also inline on home)
├── /careers             Driver/staff applications (from the v3 "Drive with GEA" section)
├── /privacy, /terms     From Legal.dc.html
├── /portal/*            Customer portal (optional login): shipments, invoices
└── /admin/*             Filament back office
    ├── Dashboard (KPIs, alerts, AI daily digest)
    ├── CRM: Leads, Contacts/Companies, Pipeline board, Follow-ups, AI Conversations
    ├── Operations: Shipments, Dispatch board, Live map, Manual update console
    ├── Fleet: Trucks, Maintenance; Drivers
    ├── Finance: Quotes/Proformas, Invoices, Payments, Expenses, Reports
    ├── Content: Website content, FAQ, AI knowledge base
    └── Settings: Company & tax, Users & roles, Integrations (AI, tracking connectors, mail), Audit log
```

## 7. Website (port of the v3 design)

**Source:** `/design-draft/GEA Website v3.dc.html`, `Service.dc.html`, `Legal.dc.html`, `coverage-map.html` and `assets/`.

**Porting rules:**
1. **Convert each `.dc.html` into Blade layouts and partials.** Remove the `support.js` runtime and the in-browser React/Babel. The output is plain server-rendered HTML.
2. **Keep the visual design:** Barlow / Barlow Condensed, the navy/blue/gold palette, layout, copy and sections.
3. **Content becomes editable in the admin:** services, FAQ, values, contact details, hero text, career roles.
4. **Compress the videos.** `hero-truck.mp4` is 12 MB today. Target 2–3 MB each at 720p, H.264 + WebM, using the poster images that already exist. Lazy-load the non-hero videos. Keep the existing pause control.
5. **Do not deploy** the `uploads/` folder. It contains duplicate media and GEA's questionnaire PDFs.
6. **Confirm video licences** for commercial use before go-live.

**Content fixes (must do before GEA's review):**

| Item in draft | Action |
|---|---|
| "Across Nigeria and West Africa", "36 + FCT", cross-border drivers | Hide behind a setting until GEA confirms coverage (Open Question 2) |
| Credential badges (goods-in-transit insurance, haulage licence, membership) | Show only the ones GEA provides proof for. CAC RC 9198613 is confirmed |
| Six placeholder client-logo slots | Hide the section until real logos with permission arrive |
| Six core values | Confirm with GEA. Their text only gave Integrity in full |

**Forms (quote, careers, contact):** server-side validation, honeypot plus rate limit (no paid captcha), and a stored record (quotes create a **Lead**, Section 9). Send an email notification to sales@ and an auto-reply to the customer.

**SEO:** per-page meta and OpenGraph, `sitemap.xml`, `robots.txt`, LocalBusiness JSON-LD with the Lagos address, and a Google Business Profile link.

## 8. Hybrid tracking

### 8.1 Principle
A shipment's live status comes from **any** of these sources, merged into one timeline:
1. **Driver phone GPS** (Android app). This is the primary source.
2. **Manual update by staff** (admin console). Used when signal drops, the driver has no smartphone, or for checkpoint calls ("Driver called from Ore, 2:15pm").
3. **Future tracker connector**: a GPS tracker company's API or a Traccar server, plugged in without changing the rest of the system.

Whichever source updates, the **customer's tracking page and the route line update within 15 seconds** through polling.

### 8.2 Shipment stage lifecycle
```
BOOKED → ASSIGNED → LOADING → LOADED → IN_TRANSIT → ARRIVED → DELIVERED → CLOSED
                                     ↘ DELAYED (reason) ↗
any → CANCELLED (reason; Ops/Management only)
```
- Each transition records the source (`driver_app` | `manual` | `connector` | `system`), the user, time, optional location and note.
- DELIVERED requires proof of delivery: a photo or signature from the app, or for a manual update, an uploaded scan of the signed waybill.
- DELIVERED automatically creates a **draft invoice** for Finance.

### 8.3 Location model
```
positions: id, shipment_id, truck_id, source[phone|manual|connector], lat, lng, accuracy_m, speed_kmh,
           heading, place_label (e.g. "Ore, Ondo"), recorded_at, received_at
```
- **Phone:** the app sends every 60s while IN_TRANSIT (every 5 min when stationary). It batches and queues offline, then uploads on reconnect.
- **Manual:**
  - Staff pick a place on the map, or type a town and it is geocoded with Nominatim and cached.
  - They can also choose from a list of common checkpoints/towns on GEA's routes, editable in admin.
  - Each manual update has a note field.
- **Merging rules:**
  - Show the newest position whatever its source.
  - Label the source to staff; the customer sees "Updated 5 min ago".
  - Flag a trip as **stale** if there's been no update for 60 minutes while IN_TRANSIT. This alerts Ops and is included in the AI digest.
- **Route line:**
  - Planned route: a straight or simplified polyline from origin to destination, with optional waypoints entered by Ops.
  - Travelled path: the polyline of received positions, drawn in GEA blue.
  - The truck marker sits at the latest position.
- **ETA:** remaining distance divided by an average speed (configurable, default 45 km/h), adjusted for DELAYED. Shown as a time window, e.g. "Expected 4–6pm".
- **Retention:** keep raw positions for 90 days, then downsample to one point per 10 minutes.

### 8.4 Tracker connector slot (for future integration)
```php
interface TrackingConnector {
    public function name(): string;                       // e.g. "Traccar", "VendorX"
    public function fetchPositions(Carbon $since): iterable; // returns DTOs: device_id, lat, lng, speed, recorded_at
    public function supportsWebhook(): bool;
    public function handleWebhook(Request $request): iterable;
}
```
- A `trucks.tracker_device_id` field maps a device to a truck.
- A scheduled job polls the connectors every minute. A webhook route `POST /api/connectors/{name}/webhook` is protected by a per-connector secret.
- **v1 ships with:**
  - `PhoneConnector` (internal)
  - `ManualConnector` (internal)
  - `TraccarConnector` (disabled; works when a Traccar server URL and token are added)
  - A **generic JSON webhook connector**, so a local tracker company can push positions in a documented format.
- Add a short **Integration guide** page in admin settings showing the webhook URL and the JSON format.

### 8.5 Public tracking page
- Search by tracking code in the format `GEA-YYMM-XXXXX`, five random characters with no ambiguous letters. Rate-limited by IP.
- **Shows:**
  - Origin → destination
  - Current stage with a timeline (timestamps and notes marked public)
  - A Leaflet map with the route line and truck marker
  - "Updated X min ago" and the ETA window
- **Never shows:** price, driver phone, the full truck plate (masked as `LAG-***-XY`) or private notes.
- Polls every 15 seconds while the page is open. New stages animate into the timeline and the marker glides to its new position.
- "Get updates" lets the customer opt in to email updates. SMS/WhatsApp are added only if the paid add-ons are switched on.

## 9. CRM

### 9.1 Entities
```
companies: id, name, industry, address, tin, notes
contacts: id, company_id?, name, phone, email, whatsapp, role, source, consent_marketing
leads: id, contact_id, company_id?, source[website_form|ai_chat|whatsapp_click|phone|walk_in|referral|careers],
       service_interest, origin, destination, cargo, tonnage, preferred_date, estimated_value_kobo,
       stage[new|contacted|qualified|quoted|won|lost], lost_reason, intent_score(0–100), owner_user_id,
       ai_summary, next_follow_up_at
activities: id, lead_id/contact_id, type[call|email|whatsapp|meeting|note|ai_chat|stage_change], body, user_id, at
follow_ups: id, lead_id, due_at, assigned_to, status[open|done|missed], note
```

### 9.2 Behaviour
- **Every website quote form and every AI chat that collects contact details** creates or updates a Contact and Lead. Duplicates are matched by phone/email.
- **Pipeline board** (Kanban by stage) with drag to change stage. "Quoted" links to a Proforma.
- **Won:** one click converts the lead into a Customer, with an optional first Shipment and Invoice.
- **Follow-ups:**
  - A new lead gets an automatic follow-up due within 1 working day, assigned round-robin to Sales.
  - Overdue follow-ups appear on the dashboard and in the daily digest.
- **Reports:** leads by source, conversion rate by stage, response time, won value by month.
- Phone and walk-in enquiries are logged by staff in a quick-add form (3 fields minimum).

## 10. Operations and finance

### 10.1 Shipments and dispatch
- Create a shipment with: customer, cargo, tonnage, origin/destination, pickup date, waybill number and notes.
- Assign a truck (filtered by capacity ≥ load and status = available) and a driver.
- **Dispatch board** by stage, plus a **live map** of all active trucks with their last update and its age.
- **Manual update console:** one screen listing active trips. For each trip, staff can change the stage, set a location or checkpoint, and add a note. Designed for speed (2 clicks).

### 10.2 Fleet and drivers
- **Trucks:** registration, type, capacity, make/model/year, `tracker_device_id`, status, and document expiry dates (insurance, roadworthiness, licence) with alerts 30, 14 and 7 days before expiry.
- **Maintenance records:** each cost posts to Expenses.
- **Drivers:** name, phone, licence number and expiry, guarantor, photo, assigned truck, trips, on-time rate. Also a flag for whether they have a smartphone, which decides app vs. manual tracking.

### 10.3 Invoicing (unchanged rules from v1; must match the 5 approved templates)
```
subtotal     = Σ (qty × unit_price)
taxable      = subtotal − discount
vat          = taxable × vat_rate / 100     (default 7.5; editable; 0 for exempt)
grand_total  = taxable + vat
balance_due  = grand_total − amount_paid − wht_deducted
```
- Store money as integer **kobo** (`BIGINT`). Use `brick/money` or bcmath. Never use floats. Round VAT half-up.
- Amount in words is generated automatically (English, naira and kobo).
- **Numbering:** `GEA-INV-YYYY-#####` and `GEA-PRO-YYYY-#####`, sequential with no gaps. Use a DB row lock on a counters table.
- **Statuses:** DRAFT → ISSUED → PARTIALLY_PAID → PAID, plus OVERDUE and VOID (reason required). Never delete.
- **Five output templates** as Blade/DomPDF, matching the approved designs:
  - Corporate Classic
  - Bold Navy
  - Haulage Trip (the default for shipment invoices; includes waybill, truck, driver, cargo, origin, destination and dates)
  - E-Invoice
  - Proforma/Quotation
- **FIRS e-invoicing readiness:**
  - Store the supplier TIN, VAT number, buyer TIN, and the `irn`, `qr_payload`, `stamp` and `einvoice_status` fields.
  - Implement an `EInvoiceProvider` interface with a **mock adapter** in v1. The real FIRS or access-point adapter is added once GEA confirms its compliance phase and credentials.
  - If an issued invoice requires e-invoicing and has no IRN, flag it.
- **Delivery:** email the PDF from billing@. A "Copy WhatsApp message" button builds a wa.me link with the invoice summary and portal link (free).
- **Reminders by email:** 3 days before due, on the due date, then weekly while overdue.

### 10.4 Document export (PDF + Word)
Every customer-facing document and report can be downloaded as **PDF** and **Word (.docx)**:
- **Documents:** invoices, proformas/quotes, delivery notes/waybills, customer statements.
- **Reports:** revenue, expenses, profit per trip/truck, receivables ageing, VAT summary, CRM pipeline.
- **Word versions** use the same GEA branding (logo, colours, header/footer) through PhpWord templates, so staff can edit and re-send them.
- Bulk export: select several records → download a ZIP of PDF or .docx files.

### 10.5 Accounting
- **Payments:**
  - Recorded with method, reference, amount and WHT, and allocated to one or more invoices.
  - Partial payments allowed.
  - Customer credit balance supported.
- **Expenses:**
  - Categories: fuel, maintenance, tolls/levies, driver allowances, salaries, rent, insurance, other.
  - Each can be linked to a truck/shipment, with a receipt upload.
- **Reports** (date-filtered; PDF, Word and CSV):
  - Revenue by customer, route, truck and month
  - Expenses by category and truck
  - **Profit per trip and per truck**
  - Receivables ageing (0–30, 31–60, 61–90, 90+ days)
  - VAT summary
  - WHT receivable

### 10.6 Management dashboard (Filament widgets)
- **KPIs:** active trips, stale trips, trucks available/on trip/in maintenance, deliveries this month, on-time %, revenue this month vs last, receivables, overdue invoices, new leads this week, follow-ups due.
- **Charts:** 12-month revenue, trips per week, leads by source, pipeline value.
- **Mini live map. Alerts panel:** stale or delayed trips, expiring documents, overdue invoices, missed follow-ups, AI handoffs waiting.
- **AI daily digest card** (Section 12.5).

## 11. Notifications

| Event | Default channel (free) | Optional paid add-on |
|---|---|---|
| Shipment booked (tracking link) | Email to customer; staff copy a wa.me message with one click | SMS (e.g. Termii), WhatsApp Business API |
| Stage changes / delay | Email (if opted in) | SMS / WhatsApp |
| New lead / follow-up due | Email + admin bell notification | — |
| Invoice issued / reminder / receipt | Email from billing@ | WhatsApp |
| Stale trip, document expiry, overdue invoice | Admin notification + daily digest email to Management | SMS to Ops lead |

Build a `NotificationChannel` setting per event, so paid channels can be switched on later without code changes.

## 12. AI system (free API)

### 12.1 Providers and cost
- **Primary: Google Gemini API, free tier** (a current "Flash" model). **Fallback: Groq API, free tier** (an open model such as Llama). Wrap both behind:
```php
interface AiProvider {
    public function chat(array $messages, array $tools = [], array $options = []): AiResponse; // supports function/tool calling
}
```
- **Free tiers have daily and per-minute limits.** If both providers are rate-limited:
  - The assistant switches to "Leave your details and our team will reply". It still shows the lead form and tracking box, so no lead is lost.
  - Management gets an alert.
- **Data caution:** on free tiers the provider may use prompts to improve its models.
  - **Never send invoice amounts, bank details, payment data or other customers' data to the AI.**
  - Shipment status sent to the AI is limited to stage, city-level location and ETA.
  - Mention AI use in the privacy policy.
- Keep the API keys server-side only. The browser talks to `POST /api/assistant/message` on our Laravel server, never to the AI provider directly.
- **Usage controls:**
  - Max 20 turns per conversation.
  - A per-IP rate limit.
  - Answers to the top FAQs are cached.
  - A daily usage counter is shown in admin.

### 12.2 Knowledge (answers only from GEA's content)
- A `knowledge_items` table, editable in admin, holds: services, fleet and capacity, coverage, process steps, FAQ, contact details, office hours, careers, values, mission/vision. Seed it from the website content.
- **Grounding:** each request includes the relevant knowledge items, chosen by keyword/category match (simple retrieval, no paid vector DB), plus the page the visitor is on.
- **System rules:**
  - Answer only from the knowledge. If unsure, say so and offer a human.
  - Never invent prices, ETAs, policies or coverage.
  - Reply briefly in clear English and understand Nigerian Pidgin.
  - Always offer a next step: quote, track, call or WhatsApp.

### 12.3 Proactive website assistant (engages visitors)
- A **chat bubble on every public page**. It opens with a short, context-aware prompt, never an auto-open full window:

| Trigger | Prompt shown |
|---|---|
| 8–10s on home page, or 40% scroll | "Moving goods somewhere? I can get you a quote or track a shipment." |
| On a service page | "Questions about container haulage? Ask me, or get a quote in 1 minute." |
| Focus on the tracking box but no submit | "Need help finding your tracking code?" |
| Exit intent (desktop) or 60s idle on the quote section | "Want our team to call you back instead?" |

- Show **once per session** per trigger. Visitors can dismiss it ("Don't show again" is stored in a cookie).
- **No personal data is stored until the visitor chats and gives it.** Show a one-line notice: "Chats are saved to help our team respond."
- Visual: GEA navy bubble with the gold accent, matching v3. **Reduced motion is respected.**

### 12.4 AI tools (function calling, executed by Laravel)

| Tool | Does | Guard |
|---|---|---|
| `track_shipment(code)` | Returns stage, city-level location, last update, ETA window | Public-safe fields only |
| `create_or_update_lead(name, phone, email?, service, origin, destination, cargo, tonnage, date)` | Creates a CRM Contact and Lead with source `ai_chat`, attaches the chat transcript and an AI summary, and sets intent_score | Confirms details back to the user before saving |
| `request_callback(name, phone, preferred_time)` | Creates a follow-up for Sales | — |
| `handoff_to_human(reason)` | Marks the conversation for staff and notifies Sales | Always available. Used automatically for complaints, damage/loss, payments or disputes, or when the user asks |
| `get_faq(topic)` | Pulls knowledge items | — |

- **Admin "AI Conversations" inbox:** staff read transcripts, take over a chat (the visitor sees "A GEA team member has joined"), and reply. Replies are delivered while the visitor is still on the page, via polling.

### 12.5 Operations assistant (staff side)
- **Daily digest** at 7:00am Lagos time, sent by email to Management/Ops and shown on the dashboard. It is built from **database queries first**, and the AI only turns the facts into a short summary. It covers:
  - Active and stale trips, delayed trips with reasons
  - Today's pickups and expected deliveries
  - Overdue invoices and total receivables
  - Expiring truck and driver documents (next 14 days)
  - New leads, hot leads (intent ≥ 70) and missed follow-ups
- **Real-time alerts** (rule-based, no AI needed): a trip goes stale, a shipment is marked DELAYED, an invoice becomes overdue, a lead isn't contacted within 1 working day.
- **"Ask the system"** box for Management, in plain language, e.g. "Which trucks made the most money in October?" or "List customers owing more than ₦500k".
  - Implemented with **whitelisted report tools**, never free-form SQL.
  - Financial results are computed by Laravel and **summarised without sending raw financial data to a free-tier AI**.
  - If this rule can't be met on the free tier, the feature stays limited to non-financial questions.

## 13. Android driver app (Kotlin)
- Login with phone number + PIN or OTP (OTP by email in v1, to stay free), using a Sanctum token.
- **My trips:** assigned trips with pickup/drop-off, cargo, and an "Call Ops" button.
- **Big stage buttons:** Start loading → Loaded → Start trip → Arrived → Delivered.
- **Background GPS:**
  - A foreground service with a persistent notification while a trip is IN_TRANSIT.
  - Fused Location Provider, 60s interval, battery-aware.
  - Requests the right Android permissions (fine plus background location, notifications) with clear explanations.
- **Offline-first:**
  - Positions and stage events are stored in Room.
  - WorkManager uploads in batches when online.
  - The server de-duplicates on `client_event_id`.
- **Proof of delivery:** camera photos, receiver name, on-screen signature.
- **Report issue:** breakdown, accident, delay or checkpoint, with a photo. Alerts Ops immediately.
- Distributed as a signed APK first (free), then on Google Play under GEA's developer account.

## 14. Website motion spec (keep it premium, keep it light)
The v3 design already follows restrained, high-end rules: one orchestrated motion moment, 44px tap targets, focus-visible states, a video pause control, a mobile menu. Keep these and add:
- **Hero:** the existing video, with the headline revealed line by line (mask slide-up, 0.04s stagger) and the tracking bar fading up last with a gold focus ring.
- **Section reveals:** fade + 24–40px rise when 15% in view, run once. Counters (35 trucks, 5–30 t, 2020) count up.
- **Coverage map:** routes from Lagos draw in on scroll (SVG stroke animation). The scope shown follows the confirmed coverage setting.
- **Tracking page:** the route line draws in and the truck marker glides to each new position.
- **Chat bubble:** a gentle slide-in, never bouncing or looping.
- **Rules:**
  - Animate only `transform` and `opacity`.
  - Load GSAP from CDN with `defer`, only on pages that use it.
  - Pause animations when off-screen.
  - Honour `prefers-reduced-motion` (no animation, static end states).
  - Keep total page JS under 120 KB on public pages, excluding the map.

## 15. Data model summary (MySQL)
`users, roles/permissions, companies, contacts, leads, activities, follow_ups, customers, trucks, maintenance_records, drivers, shipments, shipment_events, positions, proof_of_deliveries, tracking_connectors, quotes(proformas), invoices, invoice_lines, payments, payment_allocations, expenses, counters, knowledge_items, ai_conversations, ai_messages, ai_usage_daily, website_content, notification_settings, activity_log`

All money columns are `BIGINT` kobo. All tables have timestamps; financial tables use soft deletes or a VOID status only.

## 16. Non-functional requirements
- **Hosting fit:** must run on FigureWeb cPanel shared hosting: no daemons, cron-driven jobs, polling instead of sockets. Document the steps to move to a VPS if needed.
- **Performance:** public pages are server-rendered and cached (full-page cache for marketing pages). Admin list pages are paginated. The tracking poll endpoint answers from an indexed query in under 100ms.
- **Security:**
  - HTTPS, CSRF, form validation, rate limits
  - Argon2/bcrypt passwords, 2FA for admin roles
  - Signed URLs for documents
  - Secrets only in `.env`; `/storage` not publicly listable
  - Webhook secrets per connector
- **Data protection:** comply with the **Nigeria Data Protection Act 2023**. That means:
  - A privacy policy, which includes the AI chat notice
  - Consent for marketing
  - Data minimisation for the AI (Section 12.1)
  - Export or delete a contact's data on request
- **Backups:** daily database and uploads backup (JetBackup/cPanel or `spatie/laravel-backup`) with a copy off the server. Keep 30 days.
- **Accessibility:** WCAG 2.1 AA on public pages and the tracking page.
- **Devices:** latest Chrome, Safari, Edge and Firefox; Android 9+ for the driver app.

## 17. Delivery plan and acceptance

Phases follow the proposal. Payment milestones: 50% on approval, 30% at core system delivery, 20% at go-live.

| Phase | Scope | Acceptance criteria |
|---|---|---|
| **0. Foundation** | Laravel 11 + MySQL, Filament, roles/permissions, audit log, settings, CI (GitHub Actions: Pint, PHPStan level 6, Pest), deploy script for cPanel | Staff log in by role on a staging subdomain. CI green |
| **1. Website** | Port v3 to Blade, content editing, compressed media, forms → leads, SEO, motion (Section 14), content fixes (Section 7) | All pages live on staging. Lighthouse targets met. Forms create leads. Reduced motion works |
| **2. CRM** | Contacts, companies, leads, pipeline board, follow-ups, activities, reports | Website quote → lead → follow-up assigned → convert to customer |
| **3. Operations + hybrid tracking** *(30% milestone)* | Shipments, stages, dispatch, manual update console, public tracking with Leaflet map, polling, connector interface + generic webhook | A test shipment is updated manually and through the webhook. The customer page reflects each update within 15s with the route line |
| **4. Driver app** | Kotlin app: login, trips, background GPS, stages, POD, offline queue | A live test trip shows phone positions on the customer page. An offline drive syncs correctly |
| **5. Finance + documents** | Quotes, invoices (5 templates), kobo maths, numbering, payments, WHT, expenses, reports, **PDF + Word export**, e-invoice mock | All finance tests pass (Section 19). Invoice auto-drafted on DELIVERED. .docx files open correctly in Word |
| **6. AI** | Gemini + Groq providers, knowledge base, proactive bubble, tools, CRM capture, human takeover inbox, daily digest, alerts, usage limits | Bot answers only from knowledge, tracks a code, captures a lead with summary, hands off on request, degrades gracefully when rate-limited, and sends no financial data to the AI |
| **7. Pilot and launch** *(20% milestone)* | Data import (CSV/Excel) of customers, trucks, drivers, routes; pilot with selected trucks; staff training per role; go-live; backups and monitoring checked | Two-week pilot with no P1 bugs. Staff trained. Backups restored successfully in a test |
| **Ongoing (12 months)** | Hosting, support, updates | Uptime ≥ 99.5%. Support response within 1 business day |

## 18. Building with Claude Code

### 18.1 Repo layout
```
gea-platform/
├── PRD.md                     ← this file
├── CLAUDE.md                  ← conventions + commands
├── design-draft/              ← unzipped Claude Design export (reference only; not deployed)
├── backend/                   ← Laravel 11 app (website + admin + API)
│   ├── app/Domain/{Crm,Shipping,Tracking,Finance,Ai,Content}
│   ├── app/Filament/          ← admin resources & widgets
│   ├── resources/views/site/  ← Blade port of v3
│   ├── resources/views/pdf/   ← 5 invoice templates
│   └── tests/                 ← Pest
├── android-driver/            ← Kotlin app (Gradle)
└── docs/                      ← deploy-to-cPanel guide, connector integration guide, user guides
```

### 18.2 Conventions
- PHP 8.2+, strict types, Laravel Pint, PHPStan (Larastan) level 6+, Pest tests.
- Domain logic lives in `app/Domain/*` services, never in controllers or Filament pages. Money only through a `Money` value object in kobo.
- Every phase ends with `composer test && composer analyse` green.
- **Seed data:**
  - 35 trucks (5–30 t), 12 drivers (8 with smartphones), 15 customers
  - 40 shipments across Nigerian routes, 30 leads, 25 invoices
  - Knowledge items from the website copy
- `.env.example` is kept current. No secrets in the repo.
- Must run on shared hosting: no Redis, no long-running processes, cron only.

### 18.3 Phase prompts (paste into Claude Code one at a time)
1. *"Read PRD.md. Set up Phase 0: a Laravel 11 + MySQL app in /backend with Filament 3, spatie/permission roles from Section 4, activity log, settings, Pest, Larastan and Pint, plus a GitHub Actions CI. Add a cPanel deploy guide in /docs. Write CLAUDE.md from Section 18.2."*
2. *"Phase 1: port /design-draft 'GEA Website v3.dc.html', Service.dc.html and Legal.dc.html into Blade (Section 7). Remove the support.js/React/Babel runtime, keep the design, make content editable in Filament, compress the videos, apply the content fixes table, and add the motion rules in Section 14. Website forms must create leads."*
3. *"Phase 2: build the CRM in Section 9: contacts, companies, leads, pipeline Kanban, follow-ups with round-robin assignment, activities and reports."*
4. *"Phase 3: build shipments, the stage machine (8.2), dispatch board, manual update console (10.1), the positions model and merging rules (8.3), the TrackingConnector interface with Manual, Phone, Traccar (disabled) and generic webhook connectors (8.4), and the public tracking page with Leaflet and 15s polling (8.5). Test every transition."*
5. *"Phase 4: in /android-driver build the Kotlin app in Section 13 against the Laravel Sanctum API: login, trips, foreground-service GPS, stage buttons, POD capture, Room offline queue and WorkManager sync."*
6. *"Phase 5: build finance (10.3–10.5): quotes, invoices with kobo maths and gap-free numbering, the 5 DomPDF templates, PDF + PhpWord .docx export including bulk ZIP, payments, WHT, expenses, reports and the EInvoiceProvider interface with a mock adapter. Make every case in Section 19 pass."*
7. *"Phase 6: build the AI system in Section 12: the AiProvider interface with Gemini (primary) and Groq (fallback), the knowledge base, the proactive chat bubble with triggers, function tools, CRM lead capture, the human takeover inbox, the daily digest and alerts, usage limits and the data-minimisation guards."*
8. *"Phase 7: add CSV/Excel import for customers, trucks, drivers and routes, the management dashboard widgets (10.6), backups, monitoring, a go-live checklist and short user guides per role in /docs (exportable to Word)."*

## 19. Required finance tests

| Case | Input | Expected |
|---|---|---|
| Basic | 2 × ₦150,000 + 1 × ₦50,000; discount ₦10,000; VAT 7.5% | subtotal 350,000; taxable 340,000; VAT 25,500; total 365,500 |
| Paid + WHT | total 365,500; paid 100,000; WHT 17,000 | balance 248,500 |
| Exempt | VAT 0% | VAT 0; total = taxable |
| Rounding | taxable ₦1,000.33; VAT 7.5% | VAT ₦75.02 |
| Void | Void a paid invoice | Blocked until a refund/credit note exists |
| Numbering | 3 invoices created at the same time | Unique, sequential, no gaps |
| Word export | Export the "Basic" invoice to .docx | Opens in Word; totals identical to the PDF |

## 20. Accounts and items needed

| Item | Owner | Status |
|---|---|---|
| Domain geahaulageandlogistics.com, DNS, SSL | FigureWeb | Quoted |
| cPanel account (PHP 8.2+, MySQL, SSH, cron) | FigureWeb | To set up |
| Mailboxes: atueyigodwin@, info@, billing@, support@, sales@, logistic@ (6 requested vs 5 quoted) | FigureWeb | **Decision pending** |
| Gemini API key (free tier) + Groq API key (free tier) | FigureWeb | To create |
| Google Play developer account (GEA-owned) | GEA + FigureWeb | Later; APK first |
| GEA TIN, VAT number, bank details for invoices | GEA | **Pending** |
| FIRS e-invoicing status and credentials | GEA Finance | **Unknown** |
| Real photos/video of trucks, yard, team; client logos with permission; credential proofs | GEA | **Pending** |
| List of common routes and checkpoints for manual updates | GEA Ops | To collect |
| Which drivers have Android smartphones | GEA Ops | To collect |

## 21. Open questions
1. Coverage: Nigeria only, or West Africa too? This drives website copy and the coverage map.
2. Are all 8 services offered today (especially warehousing, freight forwarding and petroleum haulage)?
3. The six core values: confirm the wording.
4. FIRS e-invoicing: GEA's VAT registration, TIN and compliance phase.
5. Sixth mailbox: add it (₦10,000/yr) or make it an alias?
6. Who approves designs, and who is trained for each role?
7. How many drivers have smartphones? Will GEA provide phones for those who don't?
8. Are the hero videos licensed for commercial use?
9. Existing records to import, and in what format?
10. Do customers need portal logins at launch, or is public tracking plus emailed invoices enough?
11. When GEA wants SMS/WhatsApp alerts, who pays the per-message costs?

---
*Prepared by FigureWeb (Figuraid Ltd.) for GEA Haulage and Logistics. Innovate • Connect • Grow.*
