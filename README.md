# GEA Haulage and Logistics

This repo holds the GEA Logistics Platform (`PRD.md`): a Laravel app in `backend/` (website, back office, API), with the driver app and docs to follow. See `CLAUDE.md` for conventions and `docs/deploy-cpanel.md` for deployment.

## Website (Phase 1)

The public website is now part of the Laravel app in `backend/`, ported from the approved "GEA Website v3" design in `design-draft/`. Staff edit its content in the admin under **Content**. See `backend/README.md` to run it.

Still needed from GEA for the website: the logo files (`logo-blue.png`, `logo-white.png`; the site shows a text wordmark until they are added to `backend/public/assets/img/`), confirmation of cross-border coverage, proof of any credentials beyond CAC registration, client logos with permission, legal review of the privacy policy and terms, and licence confirmation for the stock videos.

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
