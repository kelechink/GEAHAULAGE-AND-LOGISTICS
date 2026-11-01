# GEA Logistics Platform: backend

Laravel 12 app for the GEA website, the Filament back office at `/admin`, and the API. See `../PRD.md` for scope and `../CLAUDE.md` for conventions.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

Open http://127.0.0.1:8000/admin. Demo users (local only, password `password`):

| Role | Email |
|---|---|
| Super Admin | admin@example.test |
| Management / MD | md@example.test |
| Operations | ops@example.test |
| Sales / Customer Service | sales@example.test |
| Finance / Accounts | finance@example.test |
| Driver (no admin access) | driver@example.test |
| Customer (no admin access) | customer@example.test |

## Checks

```bash
composer lint && composer analyse && composer test
```

Deploying: see `../docs/deploy-cpanel.md`.
