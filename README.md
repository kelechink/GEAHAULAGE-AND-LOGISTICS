# GEA Haulage and Logistics

This repo holds the GEA Logistics Platform (`PRD.md`), built by FigureWeb. See `CLAUDE.md` for conventions.

| Folder | What |
|---|---|
| `backend/` | Laravel 12 app: public website, Filament back office at `/admin`, public tracking, driver and assistant APIs. See `backend/README.md` to run it. |
| `android-driver/` | Kotlin driver app (login, trips, GPS, stage buttons, proof of delivery, offline queue). See `android-driver/README.md`. |
| `docs/` | Deploy guide, backups and monitoring, go-live checklist, connector guide, user guides per role (Markdown and Word). |
| `design-draft/` | The approved "GEA Website v3" design, reference only. |

## What is built

1. **Foundation:** roles and permissions, audit log, company and tax settings, CI (lint, static analysis, tests on SQLite and MySQL).
2. **Website:** the v3 design in Laravel, editable content, quote form into the CRM, careers, privacy and terms, sitemap.
3. **CRM:** leads, pipeline, follow-ups with round-robin assignment, contacts, companies, customers, reports.
4. **Operations and tracking:** shipments with enforced stages, dispatch board, two-click update console, live map, tracker connectors, public tracking page with map and 15-second polling.
5. **Driver app:** Sanctum API and the Android app, offline-first.
6. **Finance:** quotes, invoices in kobo with gap-free numbering, five PDF templates plus Word, payments with WHT, credit notes, expenses, reports, FIRS e-invoice readiness.
7. **AI assistant:** website chat (Gemini, Groq fallback) grounded in GEA's knowledge, lead capture, tracking, human takeover; 7am digest; rule-based alerts; "Ask the system" for Management.
8. **Go-live:** CSV/Excel import of customers, trucks, drivers and routes; nightly backups with off-server copy; `/health` for UptimeRobot; error emails; go-live checklist and user guides.

The admin dashboard shows income, expenditure, quote requests, trips and alerts in colour charts for each role.

## Still needed from GEA

Confirmation of cross-border coverage, office hours for the assistant, proof of credentials beyond CAC registration, client logos with permission, legal review of the privacy policy and terms, licence confirmation (or replacement) for the stock videos, AI and mail credentials, and the real customer, truck, driver and route lists for import. `docs/go-live-checklist.md` tracks all of it.

## Earlier static site

Before Phase 1, the repo root held a static one-page site for GEA Haulage and Logistics (RC 9198613), built by FigureWeb.
Target domain: geahaulageandlogistics.com

### Files

- `index.html`: all page content
- `styles.css`: styles (colour and type tokens at the top)
- `script.js`: quote form, which opens WhatsApp (0816 216 3235) with the request already written; plays the videos only while on screen
- `assets/img/gea-truck.*`: GEA-branded truck cropped from the brand mockup (a mockup, not a photo of the real fleet)
- `assets/video/`: two muted, compressed clips with poster images (about 1.4 MB in total). They are stock footage, so replace them with GEA's own footage when it's available. They don't play for visitors with reduced motion or data saver turned on.

No build step. Open `index.html` in a browser, or upload the files and the `assets` folder to any static host.

### Content sources

- Onboarding questionnaire: company details, services, fleet (35 trucks, 5 to 30 t), coverage (Nigeria and West Africa), contact
- Company profile: slogan, about, mission, vision, values, why choose GEA

### Still needed from GEA

- Logo file (SVG or transparent PNG). The header uses a text wordmark for now; the site's navy (#172F66) is taken from the logo.
- Real photos or video of GEA trucks, yard, team and loading work, to replace the stock clips
- Confirmation of service details written from the ticked boxes (e.g. ports served, example cargo types)
- Testimonials and clients they're happy to show publicly
- Licences, certifications, insurance
- Social media links
- The five professional mailboxes; the site uses gaeglobal1@gmail.com until they exist
- Approver name for sign-off
