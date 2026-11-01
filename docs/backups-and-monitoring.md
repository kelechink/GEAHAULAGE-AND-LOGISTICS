# Backups and monitoring

PRD Sections 6 and 16: daily database and uploads backup with a copy off the server, kept 30 days; UptimeRobot and email alerts for errors.

## What runs on its own

| When (Lagos time) | What | Notes |
|---|---|---|
| Every minute | Scheduler heartbeat | `/health` reports `scheduler: stale` if cron stops |
| 01:00 | `backup:clean` | Keeps every backup for 30 days, then thins out older ones |
| 01:30 | `backup:run` | MySQL dump plus uploads (proof-of-delivery photos, receipts, logos) in one zip |
| 09:00 | `backup:monitor` | Emails if the newest backup is more than a day old or the backups take over 5 GB |
| Any error | Error email | One email per error type every 30 minutes to `ALERT_EMAIL` |

Code is not in the backup because it lives in git. `.env` is not in the backup either: keep a copy of it in FigureWeb's password manager.

Backups land in `backend/storage/app/private/<APP_NAME>/` on the server. Failures go to `BACKUP_NOTIFY_EMAIL` (or `ALERT_EMAIL`).

## Off-server copy (required)

A backup that only lives on the same server is lost with the server. Use any SFTP space: a second cPanel account with SSH, a small VPS, or a storage box.

1. Create a folder there, e.g. `/backups/gea`, and an SFTP user that can write to it.
2. In `backend/.env`:

   ```dotenv
   BACKUP_DISKS=local,offsite
   BACKUP_SFTP_HOST=backup.example.com
   BACKUP_SFTP_USERNAME=gea
   BACKUP_SFTP_PASSWORD=...        # or BACKUP_SFTP_PRIVATE_KEY=/home/cpuser/.ssh/gea_backup
   BACKUP_SFTP_ROOT=/backups/gea
   ```

3. Run `php artisan config:cache && php artisan backup:run` and check the zip appears on both disks with `php artisan backup:list`.

## Restore test (do it before go-live, then every 3 months)

PRD 17 acceptance for Phase 7: "Backups restored successfully in a test." Use the staging site so live data is never touched.

1. Download the newest zip from the off-server copy (not from the live server, to prove the copy works).
2. Unzip it. It holds `db-dumps/mysql-<database>.sql` and the `private/` and `public/` upload folders.
3. On staging, import the dump: `mysql -u cpuser_gea_staging -p cpuser_gea_staging < db-dumps/mysql-cpuser_gea.sql`
4. Copy the upload folders into staging's `backend/storage/app/`.
5. Run `php artisan migrate --force` (there should be nothing to run) and `php artisan optimize:clear`.
6. Check on staging: sign in, open a recent shipment with its proof-of-delivery photo, open a recent invoice PDF, and check the newest lead is there.
7. Write the date, the backup file name and who checked it in the go-live checklist.

## Uptime monitoring (UptimeRobot, free)

Create two monitors, both every 5 minutes, alerting FigureWeb and GEA's IT contact by email:

| Monitor | Type | URL | Expect |
|---|---|---|---|
| Website | HTTP(s) | `https://geahaulageandlogistics.com/` | 200 |
| System health | Keyword | `https://geahaulageandlogistics.com/health` | keyword `"status":"ok"` |

`/health` returns 503 and `"status":"degraded"` when the database, cache or cron scheduler is down. It also shows `queue` (jobs waiting more than 15 minutes) and `backup` (newest backup within 2 days). It shows no business data.

## Other things to watch

- **AI usage:** Assistant → Conversations shows today's requests. If both free tiers run out, Management gets an alert and the chat switches to "leave your details".
- **Logs:** `backend/storage/logs/laravel.log`. Delete old logs if disk space runs low.
- **Disk:** cPanel shows disk use. Proof-of-delivery photos are the main growth.
