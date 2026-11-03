# GEA Driver (Android)

Kotlin app for GEA drivers with Android phones (PRD 13). Drivers without a smartphone are tracked by staff
updates in the admin Update Console instead.

## What it does

- **Sign in** with phone number and a 4–6 digit PIN. Operations sets the PIN in *Admin → Fleet → Drivers → Set app PIN*;
  setting a new PIN signs out the old phone.
- **My trips**: assigned and active trips, with one big button for the next step:
  Start loading → Loading done → Start trip → I have arrived → Confirm delivery.
- **GPS while on the road**: a foreground service records a fix every 60 s while moving and every 5 min parked,
  drops poor fixes, and uploads in batches. It stops when no trip needs tracking.
- **Proof of delivery**: receiver's name plus a photo and/or an on-screen signature.
- **Report a problem**: breakdown, accident, delay or checkpoint, with an optional photo. Operations and
  Management get an alert in the admin bell straight away. A delay also sets the trip to *Delayed*.
- **Works offline**: every action goes into a Room outbox first and is sent by WorkManager when there is signal.
  Each item has a `client_event_id`, so a resend never duplicates anything on the server.

## Modules

| Module | What | Builds without Android SDK |
|---|---|---|
| `:core` | Trip step rules, location policy, outbox planner and retry policy, phone/PIN rules. Plain Kotlin with JUnit tests. | Yes |
| `:app` | Compose UI, Retrofit client, Room outbox, WorkManager sync, location service, camera and signature. | No |

`settings.gradle.kts` only includes `:app` when it finds an Android SDK (`ANDROID_HOME`, `ANDROID_SDK_ROOT` or
`sdk.dir` in `local.properties`).

## Build

```bash
./gradlew :core:test                 # unit tests, any machine with JDK 17+
./gradlew :app:assembleDebug         # debug APK, talks to http://10.0.2.2:8000/api/ (emulator → php artisan serve)
./gradlew :app:assembleRelease -PapiBaseUrl=https://geahaulage.com/api/
```

CI (`.github/workflows/android.yml`) runs the tests and uploads a debug APK on every push that touches this folder.
For a release build, add a signing config and keystore locally; never commit the keystore.

## Local demo

1. In `backend/`: `php artisan migrate:fresh --seed && php artisan serve --host 0.0.0.0`
2. The 8 seeded drivers with smartphones have PIN `1234` (local seed only). Find their phone numbers in
   *Admin → Fleet → Drivers*.
3. Run the debug build on an emulator and sign in.

## API

All endpoints are under `/api/driver`, JSON, `Authorization: Bearer <token>`; see `backend/routes/api.php`.

| Method | Path | Notes |
|---|---|---|
| POST | `/login` | `phone`, `pin`, `device_name`. 5 tries a minute. Returns a token with the `driver` ability. |
| GET | `/me` | Profile, location intervals, Operations phone number. |
| POST | `/logout` | Revokes this token. |
| GET | `/trips` | Driver's own active trips plus ones delivered in the last day. No prices, no private notes. |
| GET | `/trips/{id}` | One trip. |
| POST | `/positions` | `positions[]` (up to 500): `client_event_id`, `shipment_id`, `lat`, `lng`, `recorded_at`, optional speed/accuracy/heading. |
| POST | `/trips/{id}/stage` | `client_event_id`, `stage` (the next step only), optional note and location. |
| POST | `/trips/{id}/deliver` | Multipart: `client_event_id`, `receiver_name`, `photo` and/or `signature`, optional location. |
| POST | `/trips/{id}/issues` | Multipart: `client_event_id`, `type` (breakdown/accident/delay/checkpoint), `note` (needed for delay), optional `photo`. |
