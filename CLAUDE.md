# GEA Logistics Platform: conventions

Read `PRD.md` first. It is the source of truth for scope, phases (Section 17) and acceptance criteria.
Build one phase at a time and check it against that phase's acceptance criteria before moving on.

## Repo layout

- `backend/`: Laravel 11 app (public website, Filament admin at `/admin`, API)
- `android-driver/`: Kotlin driver app (Phase 4)
- `design-draft/`: unzipped "GEA Website v3" Claude Design export. Reference only, never deployed
- `docs/`: deploy guide, integration guides, user guides, PRD .docx
- Repo root `index.html`, `styles.css`, `script.js`, `assets/`: the interim static one-page site, kept until the Laravel website (Phase 1) replaces it

## Commands (run inside `backend/`)

```bash
composer test       # Pest
composer analyse    # Larastan (PHPStan) level 6
composer lint       # Pint, check only
composer format     # Pint, fix
php artisan migrate:fresh --seed   # local reset with roles and demo users
```

Every phase ends with `composer lint && composer analyse && composer test` green.

## Conventions

- PHP 8.2+, `declare(strict_types=1);` in every app PHP file.
- Laravel Pint (Laravel preset), Larastan level 6+, Pest tests.
- Domain logic lives in `app/Domain/*` services (Crm, Shipping, Tracking, Finance, Ai, Content), never in controllers or Filament pages.
- Money is integer kobo (`BIGINT`) and goes through a `Money` value object. Never use floats for money. Round VAT half-up.
- RBAC with `spatie/laravel-permission`; enforce it with policies server-side. Role names live in `App\Enums\Role`.
- Audit every change to shipments, invoices, payments, expenses and leads with `spatie/laravel-activitylog` (`LogsActivity` trait).
- Company and tax settings go through `App\Settings\CompanySettings` (`spatie/laravel-settings`), not config files.
- Must run on cPanel shared hosting: MySQL, `database` queue and cache, cron-driven `schedule:run`, no Redis, no long-running processes, polling instead of websockets.
- `.env.example` stays current. No secrets in the repo.
- Tests run on SQLite in memory; production uses MySQL 8 / MariaDB 10.6+. Avoid MySQL-only SQL in migrations.
- Never send invoice amounts, bank details, payment data or other customers' data to an AI provider (PRD 12.1).

## Seed data (grows by phase)

Phase 0 seeds roles, permissions and one demo user per role (password `password`, local only).
Later phases add: 35 trucks (5–30 t), 12 drivers (8 with smartphones), 15 customers, 40 shipments on Nigerian routes, 30 leads, 25 invoices, and knowledge items from the website copy.
